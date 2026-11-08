<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/WompiEventIngressCoordinator.php';

$assertions = 0;

function red_wompi_w3_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

// Synthetic values only. None of these is a real Wompi key.
$now = 1794000000;
$eventsTest = 'test_events_SYNTHETICevents0001';
$orderId = 'ord_' . str_repeat('ab', 16);
$snapshot = hash('sha256', 'synthetic-order-snapshot');
$transaction = [
    'id' => '1234-1794000000-49201',
    'amount_in_cents' => 2490000,
    'reference' => $orderId,
    'customer_email' => 'synthetic@example.test',
    'currency' => 'COP',
    'payment_method_type' => 'PSE',
    'status' => 'APPROVED',
];
$storeOrder = [
    'orderId' => $orderId,
    'snapshotSha256' => $snapshot,
    'paymentMethod' => 'wompi_checkout',
    'paymentKind' => 'hosted',
    'currency' => 'COP',
    'totalMinor' => 2490000,
    'orderStatus' => 'pending',
    'paymentStatus' => 'pending',
    'fulfillmentStatus' => 'unfulfilled',
];

$event = static function (array $tx, string $secret = '') use ($eventsTest, $now): string {
    $secret = $secret === '' ? $eventsTest : $secret;
    $timestamp = $now - 30;
    $checksum = hash(
        'sha256',
        $tx['id'] . $tx['status'] . $tx['amount_in_cents'] . $timestamp . $secret
    );
    return json_encode([
        'event' => 'transaction.updated',
        'data' => ['transaction' => $tx],
        'environment' => 'test',
        'signature' => [
            'properties' => [
                'transaction.id', 'transaction.status',
                'transaction.amount_in_cents',
            ],
            'checksum' => $checksum,
        ],
        'timestamp' => $timestamp,
        'sent_at' => '2026-11-08T00:00:00.000Z',
    ]);
};

/**
 * Runs the coordinator with recording doubles. $overrides may replace the
 * lookup response, the stored order, or the Store Lite apply result.
 */
$run = static function (string $body, array $overrides = []) use (
    $eventsTest,
    $now,
    $transaction,
    $storeOrder
): array {
    $calls = ['lookup' => [], 'load' => [], 'apply' => []];
    $lookupTx = $overrides['lookupTx'] ?? $transaction;
    $result = RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator::handle(
        $body,
        $overrides['header'] ?? '',
        $overrides['environment'] ?? 'sandbox',
        $overrides['secret'] ?? $eventsTest,
        $now,
        $overrides['seen'] ?? [],
        static function (string $url) use (&$calls, $lookupTx, $overrides): ?string {
            $calls['lookup'][] = $url;
            if (array_key_exists('lookupBody', $overrides)) {
                if ($overrides['lookupBody'] instanceof Throwable) {
                    throw $overrides['lookupBody'];
                }
                return $overrides['lookupBody'];
            }
            return json_encode(['data' => $lookupTx]);
        },
        static function (string $id) use (&$calls, $storeOrder, $overrides): ?array {
            $calls['load'][] = $id;
            return array_key_exists('order', $overrides)
                ? $overrides['order']
                : $storeOrder;
        },
        static function (array $order, array $event) use (&$calls, $overrides): array {
            $calls['apply'][] = [$order, $event];
            return $overrides['apply'] ?? ['ok' => true, 'status' => 'applied'];
        }
    );
    return [$result, $calls];
};

try {
    // Happy path: APPROVED is applied as paid.
    [$result, $calls] = $run($event($transaction));
    red_wompi_w3_assert(
        $result['httpStatus'] === 200
            && $result['disposition'] === 'applied'
            && $result['outcome'] === 'paid',
        'verified, confirmed APPROVED event is applied as paid'
    );
    red_wompi_w3_assert(
        $calls['lookup'] === [
            'https://sandbox.wompi.co/v1/transactions/1234-1794000000-49201',
        ],
        'lookup goes only to the Sandbox transaction URL for the signed id'
    );
    red_wompi_w3_assert($calls['load'] === [$orderId], 'order loaded by the signed reference');
    $appliedEvent = $calls['apply'][0][1] ?? [];
    red_wompi_w3_assert(
        ($calls['apply'][0][0] ?? null) === $storeOrder
            && ($appliedEvent['paymentMethod'] ?? '') === 'wompi_checkout'
            && ($appliedEvent['orderSnapshotSha256'] ?? '') === $snapshot
            && ($appliedEvent['amountMinor'] ?? 0) === 2490000
            && ($appliedEvent['outcome'] ?? '') === 'paid',
        'Store Lite receives the stored order and a paid wompi_checkout event'
    );
    red_wompi_w3_assert(
        strpos(json_encode([$result, $calls['apply']]), 'synthetic@example.test') === false
            && strpos(json_encode($result), $eventsTest) === false,
        'no customer email or secret leaves the coordinator'
    );

    // Outcomes for other final statuses.
    foreach (['DECLINED' => 'failed', 'ERROR' => 'failed', 'VOIDED' => 'cancelled'] as $status => $outcome) {
        $tx = ['status' => $status] + $transaction;
        [$result, $calls] = $run($event($tx), ['lookupTx' => $tx]);
        red_wompi_w3_assert(
            $result['httpStatus'] === 200 && $result['outcome'] === $outcome
                && ($calls['apply'][0][1]['outcome'] ?? '') === $outcome,
            $status . ' is applied as ' . $outcome
        );
    }

    // PENDING is acknowledged without touching Store Lite.
    $pendingTx = ['status' => 'PENDING'] + $transaction;
    [$result, $calls] = $run($event($pendingTx), ['lookupTx' => $pendingTx]);
    red_wompi_w3_assert(
        $result['httpStatus'] === 200 && $result['disposition'] === 'pending'
            && $calls['apply'] === [],
        'PENDING is acknowledged and changes nothing'
    );

    // Forgeries and misconfiguration.
    [$result, $calls] = $run($event($transaction, 'test_events_SYNTHETICforged001'));
    red_wompi_w3_assert(
        $result['httpStatus'] === 401 && $calls['lookup'] === [] && $calls['apply'] === [],
        'a forged checksum is refused before any lookup'
    );
    [$result, $calls] = $run('{not json');
    red_wompi_w3_assert($result['httpStatus'] === 401, 'malformed JSON is refused');
    [$result, $calls] = $run($event($transaction), ['environment' => 'production', 'secret' => 'prod_events_SYNTHETICevents01']);
    red_wompi_w3_assert(
        $result['httpStatus'] === 401
            && $result['error'] === 'event_environment_mismatch'
            && $calls['lookup'] === [],
        'a Sandbox event cannot be accepted by a production endpoint'
    );
    [$result, $calls] = $run($event($transaction), ['secret' => 'prod_events_SYNTHETICevents01']);
    red_wompi_w3_assert(
        $result['httpStatus'] === 503 && $result['disposition'] === 'configuration_invalid'
            && $calls['lookup'] === [],
        'a mismatched events secret is a configuration error, retried later'
    );

    // Replay.
    [$first] = $run($event($transaction));
    [$result, $calls] = $run($event($transaction), ['seen' => [$first['eventEvidenceSha256']]]);
    red_wompi_w3_assert(
        $result['httpStatus'] === 200 && $result['disposition'] === 'replayed'
            && $calls['lookup'] === [] && $calls['apply'] === [],
        'a replayed delivery is acknowledged without side effects'
    );

    // Lookup problems are transient: Wompi should retry.
    foreach ([
        'unreachable lookup' => ['lookupBody' => null],
        'lookup exception' => ['lookupBody' => new RuntimeException('down')],
        'malformed lookup' => ['lookupBody' => '<html>'],
        'lookup still PENDING' => ['lookupTx' => ['status' => 'PENDING'] + $transaction],
        'lookup with another amount' => ['lookupTx' => ['amount_in_cents' => 100] + $transaction],
    ] as $label => $override) {
        [$result, $calls] = $run($event($transaction), $override);
        red_wompi_w3_assert(
            $result['httpStatus'] === 503 && $calls['apply'] === [],
            $label . ' returns 503 and applies nothing'
        );
    }

    // Order problems are final for this delivery.
    [$result, $calls] = $run($event($transaction), ['order' => null]);
    red_wompi_w3_assert(
        $result['httpStatus'] === 200 && $result['disposition'] === 'order_unknown'
            && $calls['apply'] === [],
        'an unknown order is acknowledged and nothing is applied'
    );
    [$result, $calls] = $run($event($transaction), ['order' => array_replace($storeOrder, ['totalMinor' => 990000])]);
    red_wompi_w3_assert(
        $result['httpStatus'] === 200 && $result['error'] === 'order_amount_mismatch'
            && $calls['apply'] === [],
        'an amount that differs from the stored order is never applied'
    );
    [$result, $calls] = $run($event($transaction), ['order' => array_replace($storeOrder, ['paymentMethod' => 'paypal'])]);
    red_wompi_w3_assert(
        $result['httpStatus'] === 200 && $result['error'] === 'order_payment_method_mismatch'
            && $calls['apply'] === [],
        'an order placed with another payment method is never applied'
    );
    [$result, $calls] = $run($event($transaction), ['order' => array_replace($storeOrder, ['currency' => 'USD'])]);
    red_wompi_w3_assert(
        $result['error'] === 'order_currency_mismatch' && $calls['apply'] === [],
        'a currency that differs from the stored order is never applied'
    );
    [$result, $calls] = $run($event($transaction), ['order' => ['orderId' => $orderId]]);
    red_wompi_w3_assert(
        $result['httpStatus'] === 503 && $calls['apply'] === [],
        'a malformed order projection is a transient failure'
    );

    // Store Lite results.
    foreach ([
        'replayed' => [['ok' => true, 'status' => 'replayed'], 200],
        'unchanged' => [['ok' => true, 'status' => 'unchanged'], 200],
        'storage down' => [['ok' => false, 'error' => 'payment_event_storage_unavailable'], 503],
        'state conflict' => [['ok' => false, 'error' => 'payment_order_state_conflict'], 503],
        'transition refused' => [['ok' => false, 'error' => 'payment_event_refused'], 200],
        'replay conflict' => [['ok' => false, 'error' => 'payment_event_replay_conflict'], 200],
        'unknown success status' => [['ok' => true, 'status' => 'done'], 503],
    ] as $label => [$applyResult, $expected]) {
        [$result] = $run($event($transaction), ['apply' => $applyResult]);
        red_wompi_w3_assert(
            $result['httpStatus'] === $expected,
            'Store Lite ' . $label . ' returns ' . $expected
        );
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

echo 'Wompi W3 event ingress self-test passed: ' . $assertions . " assertions.\n";
