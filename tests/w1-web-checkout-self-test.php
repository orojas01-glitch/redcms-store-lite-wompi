<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/WompiEnvironment.php';
require_once dirname(__DIR__) . '/src/WompiWebCheckoutPlanner.php';
require_once dirname(__DIR__) . '/src/WompiEventVerifier.php';

$assertions = 0;

function red_wompi_w1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

// Synthetic values only. None of these is a real Wompi key.
$now = 1794000000;
$publicTest = 'pub_test_SYNTHETICpublic0001';
$integrityTest = 'test_integrity_SYNTHETICintegrity0001';
$eventsTest = 'test_events_SYNTHETICevents0001';
$publicProd = 'pub_prod_SYNTHETICpublic0001';
$orderId = 'ord_' . str_repeat('ab', 16);
$snapshot = hash('sha256', 'synthetic-order-snapshot');
$order = ['orderId' => $orderId, 'amountMinor' => 2490000, 'currency' => 'COP'];
$redirect = 'https://shop.example.co/pagos/respuesta';

// Official documented integrity example (docs.wompi.co, Paso 3).
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::integritySignature(
        'sk8-438k4-xmxm392-sn2m',
        '2490000',
        'COP',
        null,
        'prod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6'
    ) === '37c8407747e595535433ef8f6a811d853cd943046624a0ec04662b17bbf33bf5',
    'integrity signature matches the documented example'
);
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::integritySignature(
        'r', '100', 'COP', '2026-01-01T00:00:00.000Z', 's'
    ) === hash('sha256', 'r100COP2026-01-01T00:00:00.000Zs'),
    'expiration time is concatenated before the secret'
);

// Environment profile.
$env = RED_CMS_Store_Lite_Wompi_Environment::resolve('sandbox', [
    'public' => $publicTest, 'integrity' => $integrityTest,
    'events' => $eventsTest, 'private' => 'prv_test_SYNTHETICprivate0001',
]);
red_wompi_w1_assert($env['valid'] === true, 'sandbox keys resolve');
red_wompi_w1_assert($env['apiBase'] === 'https://sandbox.wompi.co/v1', 'sandbox base');
red_wompi_w1_assert($env['eventEnvironment'] === 'test', 'sandbox events are test');
red_wompi_w1_assert(
    strpos(json_encode($env), 'SYNTHETIC') === false,
    'environment result carries no key value'
);
$mixed = RED_CMS_Store_Lite_Wompi_Environment::resolve('sandbox', [
    'public' => $publicProd, 'integrity' => $integrityTest,
]);
red_wompi_w1_assert($mixed['valid'] === false, 'production key refused in sandbox');
red_wompi_w1_assert(
    $mixed['errors'] === ['public_key_environment_mismatch'],
    'mismatch names the key class only'
);
$prod = RED_CMS_Store_Lite_Wompi_Environment::resolve('production', [
    'public' => $publicProd,
]);
red_wompi_w1_assert(
    $prod['valid'] === true && $prod['apiBase'] === 'https://production.wompi.co/v1',
    'production profile resolves'
);
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Environment::resolve('staging', ['public' => $publicTest])['valid'] === false,
    'unknown environment refused'
);

// Planner.
$plan = RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::plan(
    'sandbox', $publicTest, $integrityTest, $order, $redirect,
    '2026-11-07T20:00:00.000Z', strtotime('2026-11-07T19:00:00Z')
);
red_wompi_w1_assert($plan['valid'] === true, 'plan is valid');
red_wompi_w1_assert($plan['action'] === 'https://checkout.wompi.co/p/', 'checkout action');
red_wompi_w1_assert(array_column($plan['fields'], 0) === [
    'public-key', 'currency', 'amount-in-cents', 'reference',
    'signature:integrity', 'redirect-url', 'expiration-time',
], 'exact field order');
$fields = array_column($plan['fields'], 1, 0);
red_wompi_w1_assert(
    $fields['signature:integrity'] === hash(
        'sha256',
        $orderId . '2490000COP2026-11-07T20:00:00.000Z' . $integrityTest
    ),
    'planned signature binds reference, amount, currency, expiry'
);
red_wompi_w1_assert(
    strpos(json_encode($plan), $integrityTest) === false,
    'integrity secret is never returned'
);
red_wompi_w1_assert(
    $plan['transactionCreated'] === false
        && $plan['paymentVerified'] === false
        && $plan['orderMutationAuthorized'] === false,
    'planning has no payment effect'
);
$noExpiry = RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::plan(
    'sandbox', $publicTest, $integrityTest, $order, $redirect, null, $now
);
red_wompi_w1_assert(
    $noExpiry['valid'] === true && count($noExpiry['fields']) === 6,
    'expiration is optional'
);
foreach ([
    ['production', $publicTest, $integrityTest, $order, $redirect, null, 'environment'],
    ['sandbox', $publicTest, $integrityTest, ['orderId' => 'x', 'amountMinor' => 100, 'currency' => 'COP'], $redirect, null, 'reference'],
    ['sandbox', $publicTest, $integrityTest, ['orderId' => $orderId, 'amountMinor' => 99, 'currency' => 'COP'], $redirect, null, 'minimum amount'],
    ['sandbox', $publicTest, $integrityTest, ['orderId' => $orderId, 'amountMinor' => 100, 'currency' => 'USD'], $redirect, null, 'currency'],
    ['sandbox', $publicTest, $integrityTest, ['orderId' => $orderId, 'amountMinor' => '100', 'currency' => 'COP'], $redirect, null, 'string amount'],
    ['sandbox', $publicTest, $integrityTest, $order, 'http://shop.example.co/r', null, 'http redirect'],
    ['sandbox', $publicTest, $integrityTest, $order, 'https://shop.example.co/r?next=evil', null, 'redirect query'],
    ['sandbox', $publicTest, $integrityTest, $order, 'https://shop.example.co:8443/r', null, 'redirect port'],
    ['sandbox', $publicTest, $integrityTest, $order, 'https://shop.example.co/r"x', null, 'redirect quote'],
    ['sandbox', $publicTest, $integrityTest, $order, $redirect, '2026-11-07T19:01:00.000Z', 'expiry too soon'],
    ['sandbox', $publicTest, $integrityTest, $order, $redirect, '2026-11-09T19:00:00.000Z', 'expiry too far'],
    ['sandbox', $publicTest, $integrityTest, $order, $redirect, '2026-11-07T20:00:00Z', 'expiry format'],
] as [$environment, $public, $integrity, $candidateOrder, $url, $expiry, $label]) {
    $refused = RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::plan(
        $environment, $public, $integrity, $candidateOrder, $url, $expiry,
        strtotime('2026-11-07T19:00:00Z')
    );
    red_wompi_w1_assert(
        $refused['valid'] === false && $refused['fields'] === [],
        'planner refuses ' . $label
    );
}
$html = RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::formHtml($plan, 'Pagar <ahora>');
red_wompi_w1_assert(
    str_starts_with($html, '<form class="red-wompi-checkout" method="GET" action="https://checkout.wompi.co/p/">'),
    'form posts by GET to Wompi'
);
red_wompi_w1_assert(strpos($html, 'Pagar &lt;ahora&gt;') !== false, 'label escaped');
red_wompi_w1_assert(strpos($html, '<script') === false, 'no script in form');
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner::formHtml(['valid' => false], 'x') === '',
    'invalid plan renders nothing'
);

// Event verification.
$transaction = [
    'id' => '1234-1794000000-49201',
    'amount_in_cents' => 2490000,
    'reference' => $orderId,
    'customer_email' => 'synthetic@example.test',
    'currency' => 'COP',
    'payment_method_type' => 'PSE',
    'redirect_url' => $redirect,
    'status' => 'APPROVED',
    'shipping_address' => null,
];
$makeEvent = static function (
    array $tx,
    string $secret,
    int $timestamp,
    string $environment = 'test',
    array $properties = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
    ?array $signedTx = null
): array {
    $source = $signedTx ?? $tx;
    $values = '';
    foreach ($properties as $property) {
        $values .= (string) $source[substr($property, 12)];
    }
    $checksum = strtoupper(hash('sha256', $values . $timestamp . $secret));
    $body = json_encode([
        'event' => 'transaction.updated',
        'data' => ['transaction' => $tx],
        'environment' => $environment,
        'signature' => ['properties' => $properties, 'checksum' => $checksum],
        'timestamp' => $timestamp,
        'sent_at' => '2026-11-07T19:00:00.000Z',
    ]);
    return [$body, $checksum];
};

[$body, $checksum] = $makeEvent($transaction, $eventsTest, $now - 60);
$verified = RED_CMS_Store_Lite_Wompi_Event_Verifier::verify(
    $body, $checksum, 'sandbox', $eventsTest, $now
);
red_wompi_w1_assert($verified['valid'] === true, 'uppercase signed event verifies');
red_wompi_w1_assert(
    array_keys($verified['transaction']) === [
        'id', 'status', 'amount_in_cents', 'reference', 'currency',
        'payment_method_type',
    ],
    'projection is bounded'
);
red_wompi_w1_assert(
    strpos(json_encode($verified), 'synthetic@example.test') === false,
    'customer email discarded'
);
red_wompi_w1_assert(
    $verified['orderMutationAuthorized'] === false,
    'verification alone authorizes no mutation'
);
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Event_Verifier::verify(
        $body, '', 'sandbox', $eventsTest, $now
    )['valid'] === true,
    'header checksum is optional'
);
$cases = [
    'header mismatch' => [$body, str_repeat('0', 64), 'sandbox', $eventsTest, 'event_header_checksum_mismatch'],
    'wrong secret' => [$body, '', 'sandbox', 'test_events_SYNTHETICother0001', 'event_checksum_invalid'],
    'production secret in sandbox' => [$body, '', 'sandbox', 'prod_events_SYNTHETICevents0001', 'event_secret_environment_invalid'],
    'production environment' => [$body, '', 'production', 'prod_events_SYNTHETICevents0001', 'event_environment_mismatch'],
    'not json' => ['{', '', 'sandbox', $eventsTest, 'event_json_invalid'],
    'empty' => ['', '', 'sandbox', $eventsTest, 'event_body_size_invalid'],
];
$tampered = $transaction;
$tampered['amount_in_cents'] = 100;
[$tamperedBody] = $makeEvent($tampered, $eventsTest, $now - 60, 'test',
    ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], $transaction);
$cases['tampered amount'] = [$tamperedBody, '', 'sandbox', $eventsTest, 'event_checksum_invalid'];
[$oldBody] = $makeEvent($transaction, $eventsTest, $now - 90001);
$cases['too old'] = [$oldBody, '', 'sandbox', $eventsTest, 'event_time_invalid'];
[$futureBody] = $makeEvent($transaction, $eventsTest, $now + 301);
$cases['future'] = [$futureBody, '', 'sandbox', $eventsTest, 'event_time_invalid'];
[$prodBody] = $makeEvent($transaction, $eventsTest, $now - 60, 'prod');
$cases['prod event to sandbox'] = [$prodBody, '', 'sandbox', $eventsTest, 'event_environment_mismatch'];
[$oddBody] = $makeEvent($transaction, $eventsTest, $now - 60, 'test', ['transaction.shipping_address']);
$cases['unsignable property'] = [$oddBody, '', 'sandbox', $eventsTest, 'event_properties_invalid'];
$foreign = $transaction;
$foreign['reference'] = 'someone-else-123';
[$foreignBody] = $makeEvent($foreign, $eventsTest, $now - 60);
$cases['foreign reference'] = [$foreignBody, '', 'sandbox', $eventsTest, 'event_transaction_invalid'];
foreach ($cases as $label => [$caseBody, $caseHeader, $caseEnv, $caseSecret, $error]) {
    $refused = RED_CMS_Store_Lite_Wompi_Event_Verifier::verify(
        $caseBody, $caseHeader, $caseEnv, $caseSecret, $now
    );
    red_wompi_w1_assert(
        $refused['valid'] === false && $refused['errors'] === [$error],
        'verifier refuses ' . $label . ' as ' . $error
    );
}
$replay = RED_CMS_Store_Lite_Wompi_Event_Verifier::verify(
    $body, '', 'sandbox', $eventsTest, $now, [$verified['eventEvidenceSha256']]
);
red_wompi_w1_assert(
    $replay['valid'] === false && $replay['replayed'] === true
        && $replay['errors'] === ['event_replayed'],
    'replayed event refused'
);

// Reconciliation.
$lookupBody = json_encode(['data' => $transaction + ['payment_method' => ['type' => 'PSE']]]);
$lookup = RED_CMS_Store_Lite_Wompi_Event_Verifier::projectLookup($lookupBody);
red_wompi_w1_assert($lookup === $verified['transaction'], 'lookup projects identically');
$storedOrder = [
    'orderId' => $orderId, 'snapshotSha256' => $snapshot,
    'amountMinor' => 2490000, 'currency' => 'COP',
];
$reconciled = RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile($verified, $lookup, $storedOrder);
red_wompi_w1_assert(
    $reconciled['valid'] === true && $reconciled['final'] === true,
    'approved event reconciles'
);
red_wompi_w1_assert(array_keys($reconciled['storeLiteEvent']) === [
    'verification', 'replayStatus', 'outcome', 'orderId',
    'orderSnapshotSha256', 'paymentMethod', 'amountMinor', 'currency',
    'eventEvidenceSha256', 'occurredAt',
], 'store lite payment-event shape');
red_wompi_w1_assert(
    $reconciled['storeLiteEvent']['outcome'] === 'paid'
        && $reconciled['storeLiteEvent']['paymentMethod'] === 'wompi_checkout',
    'approved maps to paid'
);
$pendingLookup = $lookup;
$pendingLookup['status'] = 'PENDING';
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile($verified, $pendingLookup, $storedOrder)['errors']
        === ['event_lookup_mismatch'],
    'lookup must agree with the event'
);
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile($verified, null, $storedOrder)['errors']
        === ['lookup_unavailable'],
    'missing lookup refuses'
);
$wrongAmount = $storedOrder;
$wrongAmount['amountMinor'] = 2490001;
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile($verified, $lookup, $wrongAmount)['errors']
        === ['order_amount_mismatch'],
    'stored amount must match'
);
$otherOrder = $storedOrder;
$otherOrder['orderId'] = 'ord_' . str_repeat('cd', 16);
red_wompi_w1_assert(
    RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile($verified, $lookup, $otherOrder)['errors']
        === ['order_reference_mismatch'],
    'stored order must match the reference'
);
foreach (['DECLINED' => 'failed', 'ERROR' => 'failed', 'VOIDED' => 'cancelled', 'PENDING' => null] as $status => $outcome) {
    $tx = $transaction;
    $tx['status'] = $status;
    [$statusBody] = $makeEvent($tx, $eventsTest, $now - 60);
    $statusVerified = RED_CMS_Store_Lite_Wompi_Event_Verifier::verify(
        $statusBody, '', 'sandbox', $eventsTest, $now
    );
    $statusLookup = RED_CMS_Store_Lite_Wompi_Event_Verifier::projectLookup(json_encode(['data' => $tx]));
    $statusResult = RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile($statusVerified, $statusLookup, $storedOrder);
    red_wompi_w1_assert(
        $statusResult['valid'] === true
            && $statusResult['final'] === ($outcome !== null)
            && ($statusResult['storeLiteEvent']['outcome'] ?? null) === $outcome,
        $status . ' maps to ' . ($outcome ?? 'not final')
    );
}

echo 'Wompi W1 Web Checkout self-test passed: ' . $assertions . " assertions.\n";
