<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
$coreDirectory = getenv('RED_CMS_CORE');
if (!is_string($coreDirectory) || $coreDirectory === '') {
    $coreDirectory = dirname($projectDirectory) . '/redcms v5.1';
}
$coreInitiation = $coreDirectory
    . '/includes/addon_payment_initiation_helpers.php';
if (!is_file($coreInitiation)) {
    throw new RuntimeException(
        'RED-CMS C1 initiation helper not found; set RED_CMS_CORE.'
    );
}
require_once $coreInitiation;
require_once $projectDirectory . '/src/WompiNequiRequestPlanner.php';
require_once $projectDirectory . '/src/WompiNequiResponseGate.php';
require_once $projectDirectory . '/src/WompiNequiSealedTransportDouble.php';
require_once $projectDirectory . '/src/WompiSandboxEventVerifier.php';

$assertions = 0;

function red_wompi_c2_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c2_order(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('1', 64),
        'amountMinor' => 12500000,
        'currency' => 'COP',
        'idempotencySha256' => str_repeat('2', 64),
        'customerEmailSha256' => str_repeat('3', 64),
        'customerPhoneSha256' => str_repeat('4', 64),
    ];
}

function red_wompi_c2_acceptance(): array
{
    return [
        'privacyAccepted' => true,
        'personalDataAccepted' => true,
        'acceptanceTokenSha256' => str_repeat('5', 64),
        'personalAuthTokenSha256' => str_repeat('6', 64),
        'contractsSha256' => str_repeat('7', 64),
    ];
}

function red_wompi_c2_settings(): array
{
    return [
        'publicKeySettingPresent' => true,
        'privateKeyReferenceAvailable' => true,
        'integrityKeyReferenceAvailable' => true,
        'eventSecretReferenceAvailable' => true,
    ];
}

function red_wompi_c2_response(array $order, string $status = 'PENDING'): array
{
    return [
        'id' => '1234-1700000000-56789',
        'status' => $status,
        'amount_in_cents' => $order['amountMinor'],
        'reference' => $order['orderId'],
        'currency' => 'COP',
        'payment_method_type' => 'NEQUI',
    ];
}

function red_wompi_c2_event_checksum(array $event, string $secret): string
{
    $transaction = $event['data']['transaction'];
    $values = [];
    foreach ($event['signature']['properties'] as $property) {
        [, $field] = explode('.', $property, 2);
        $values[] = $transaction[$field];
    }
    return hash(
        'sha256',
        implode('', array_map('strval', $values))
            . (string) $event['timestamp']
            . $secret
    );
}

function red_wompi_c2_event(
    array $transaction,
    string $secret,
    int $now,
    array $properties = [
        'transaction.id',
        'transaction.status',
        'transaction.amount_in_cents',
    ]
): array {
    $event = [
        'event' => 'transaction.updated',
        'data' => ['transaction' => $transaction],
        'environment' => 'test',
        'signature' => [
            'properties' => $properties,
            'checksum' => '',
        ],
        'timestamp' => $now - 5,
        'sentAtEpoch' => $now,
    ];
    $event['signature']['checksum'] = red_wompi_c2_event_checksum(
        $event,
        $secret
    );
    return $event;
}

try {
    $order = red_wompi_c2_order();
    $acceptance = red_wompi_c2_acceptance();
    $settings = red_wompi_c2_settings();
    $plan = RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::plan(
        $order,
        $acceptance,
        $settings
    );
    red_wompi_c2_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan),
        'exact hashed COP/Nequi inputs produce one valid offline plan'
    );
    red_wompi_c2_assert(
        $plan['provider'] === 'wompi'
            && $plan['method'] === 'nequi'
            && $plan['environment'] === 'sandbox'
            && $plan['targetHost'] === 'sandbox.wompi.co'
            && $plan['targetPath'] === '/v1/transactions'
            && $plan['httpMethod'] === 'POST',
        'plan fixes the exact provider, method, Sandbox target, and verb'
    );
    red_wompi_c2_assert(
        $plan['initiationMode'] === 'out_of_band_confirmation'
            && $plan['currency'] === 'COP'
            && $plan['amountMinor'] === $order['amountMinor']
            && $plan['orderId'] === $order['orderId'],
        'plan binds the C1 mode and immutable commercial facts'
    );
    red_wompi_c2_assert(
        $plan['wireRequestConstructed'] === false
            && $plan['secretResolution'] === false
            && $plan['networkAccess'] === false
            && $plan['providerContact'] === false
            && $plan['providerMutation'] === false
            && $plan['payment'] === false
            && $plan['webhook'] === false
            && $plan['browserNavigation'] === false
            && $plan['orderMutation'] === false
            && $plan['retryAuthorized'] === false,
        'every provider, browser, and business effect remains false'
    );
    red_wompi_c2_assert(
        preg_match('/\A[a-f0-9]{64}\z/D', $plan['requestEvidenceSha256'])
            === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $plan['acceptanceEvidenceSha256']
            ) === 1,
        'request and acceptance evidence are bounded hashes only'
    );
    red_wompi_c2_assert(
        preg_match('/\A[a-f0-9]{64}\z/D', $plan['planSha256']) === 1,
        'complete offline plan carries one deterministic self-fingerprint'
    );

    $invalidPlans = [];
    $wrongCurrency = $order;
    $wrongCurrency['currency'] = 'USD';
    $invalidPlans['non-COP order'] = [$wrongCurrency, $acceptance, $settings];
    $missingAcceptance = $acceptance;
    $missingAcceptance['personalDataAccepted'] = false;
    $invalidPlans['missing acceptance'] = [$order, $missingAcceptance, $settings];
    $missingPrivate = $settings;
    $missingPrivate['privateKeyReferenceAvailable'] = false;
    $invalidPlans['missing private-key reference'] = [
        $order, $acceptance, $missingPrivate,
    ];
    $extraOrder = $order;
    $extraOrder['customerEmail'] = 'forbidden@example.test';
    $invalidPlans['raw personal field'] = [$extraOrder, $acceptance, $settings];
    foreach ($invalidPlans as $name => $values) {
        $refused = RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::plan(
            $values[0],
            $values[1],
            $values[2]
        );
        red_wompi_c2_assert(
            !$refused['valid']
                && $refused['status'] === 'contract_refused'
                && $refused['errors'] === ['contract_refused'],
            $name . ' is refused before any executable request exists'
        );
    }

    $response = red_wompi_c2_response($order);
    $accepted = RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::accept(
        $plan,
        $response
    );
    red_wompi_c2_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::valid($accepted),
        'exact synthetic PENDING transaction is accepted'
    );
    red_wompi_c2_assert(
        $accepted['initiation'] === red_addon_payment_initiation_normalize(
            'out_of_band_confirmation',
            [
                'providerReference' => $response['id'],
                'state' => 'pending',
                'customerAction' => 'approve_in_provider_app',
            ]
        ),
        'package output exactly adopts the merged C1 core union'
    );
    red_wompi_c2_assert(
        !array_key_exists('checkoutUrl', $accepted['initiation']['value'])
            && $accepted['payment'] === false
            && $accepted['orderMutation'] === false
            && $accepted['retryAuthorized'] === false,
        'pending out-of-band result has no URL, payment, mutation, or retry'
    );

    $responseCases = [];
    $finalResponse = $response;
    $finalResponse['status'] = 'APPROVED';
    $responseCases['final status'] = $finalResponse;
    $wrongAmount = $response;
    $wrongAmount['amount_in_cents']++;
    $responseCases['amount mismatch'] = $wrongAmount;
    $wrongReference = $response;
    $wrongReference['reference'] = 'ord_ffffffffffffffffffffffffffffffff';
    $responseCases['order mismatch'] = $wrongReference;
    $wrongMethod = $response;
    $wrongMethod['payment_method_type'] = 'CARD';
    $responseCases['method mismatch'] = $wrongMethod;
    $extraResponse = $response;
    $extraResponse['checkout_url'] = 'https://example.test/forbidden';
    $responseCases['unexpected URL'] = $extraResponse;
    foreach ($responseCases as $name => $candidate) {
        $refused = RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::accept(
            $plan,
            $candidate
        );
        red_wompi_c2_assert(
            !$refused['valid']
                && $refused['status'] === 'response_refused'
                && $refused['errors'] === ['response_refused'],
            $name . ' response fails closed'
        );
    }

    $double = new RED_CMS_Store_Lite_Wompi_Nequi_Sealed_Transport_Double(
        $plan,
        $response
    );
    $doubleResult = $double->execute($plan);
    red_wompi_c2_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::valid($doubleResult),
        'sealed double emits only the bounded response-gate result'
    );
    $replay = $double->execute($plan);
    red_wompi_c2_assert(
        !$replay['valid']
            && $replay['status'] === 'transport_double_replayed'
            && $replay['retryAuthorized'] === false,
        'sealed double permanently refuses replay'
    );
    $changedDouble = new RED_CMS_Store_Lite_Wompi_Nequi_Sealed_Transport_Double(
        $plan,
        $response
    );
    $changedPlan = $plan;
    $changedPlan['requestEvidenceSha256'] = str_repeat('f', 64);
    $changed = $changedDouble->execute($changedPlan);
    red_wompi_c2_assert(
        !$changed['valid']
            && $changed['status'] === 'transport_double_plan_changed'
            && $changed['retryAuthorized'] === false,
        'changed plan consumes and refuses the one-use double'
    );

    $now = 1787443200;
    $eventSecret = bin2hex(random_bytes(32));
    $approvedTransaction = red_wompi_c2_response($order, 'APPROVED');
    $approvedEvent = red_wompi_c2_event(
        $approvedTransaction,
        $eventSecret,
        $now
    );
    $verified = RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::verify(
        $approvedEvent,
        $approvedTransaction,
        $eventSecret,
        $now
    );
    red_wompi_c2_assert(
        RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::valid($verified),
        'signed approved event plus exact lookup produce bounded evidence'
    );
    red_wompi_c2_assert(
        $verified['normalizedOutcome'] === 'paid'
            && $verified['paymentVerified'] === true
            && $verified['orderMutationAuthorized'] === false
            && $verified['providerContact'] === false
            && $verified['paymentApplied'] === false,
        'approved evidence proposes paid without applying payment or mutation'
    );
    red_wompi_c2_assert(
        !str_contains(
            json_encode($verified, JSON_UNESCAPED_SLASHES),
            $eventSecret
        ),
        'bounded event evidence excludes the synthetic event secret'
    );

    $variedEvent = red_wompi_c2_event(
        $approvedTransaction,
        $eventSecret,
        $now,
        [
            'transaction.amount_in_cents',
            'transaction.id',
            'transaction.status',
        ]
    );
    red_wompi_c2_assert(
        RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::valid(
            RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::verify(
                $variedEvent,
                $approvedTransaction,
                $eventSecret,
                $now
            )
        ),
        'provider-supplied signed properties are resolved in declared order'
    );

    foreach (['DECLINED', 'ERROR'] as $failedStatus) {
        $transaction = red_wompi_c2_response($order, $failedStatus);
        $event = red_wompi_c2_event(
            $transaction,
            $eventSecret,
            $now
        );
        $failed = RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::verify(
            $event,
            $transaction,
            $eventSecret,
            $now
        );
        red_wompi_c2_assert(
            RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::valid($failed)
                && $failed['normalizedOutcome'] === 'failed'
                && $failed['paymentVerified'] === false
                && $failed['orderMutationAuthorized'] === false,
            $failedStatus . ' normalizes only to non-mutating failure evidence'
        );
    }

    $pendingEvent = red_wompi_c2_event(
        $response,
        $eventSecret,
        $now
    );
    $pending = RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::verify(
        $pendingEvent,
        $response,
        $eventSecret,
        $now
    );
    red_wompi_c2_assert(
        !$pending['valid']
            && $pending['errors'] === ['event_status_not_final'],
        'pending event cannot propose a payment outcome'
    );

    $badEventCases = [];
    $badChecksum = $approvedEvent;
    $badChecksum['signature']['checksum'] = str_repeat('0', 64);
    $badEventCases['checksum'] = [$badChecksum, $approvedTransaction, []];
    $stale = $approvedEvent;
    $stale['timestamp'] = $now - 90001;
    $badEventCases['stale'] = [$stale, $approvedTransaction, []];
    $production = $approvedEvent;
    $production['environment'] = 'prod';
    $badEventCases['environment'] = [$production, $approvedTransaction, []];
    $unknownProperty = $approvedEvent;
    $unknownProperty['signature']['properties'][] = 'customer.email';
    $badEventCases['property'] = [
        $unknownProperty, $approvedTransaction, [],
    ];
    $changedLookup = $approvedTransaction;
    $changedLookup['amount_in_cents']++;
    $badEventCases['lookup'] = [$approvedEvent, $changedLookup, []];
    $badEventCases['replay'] = [
        $approvedEvent,
        $approvedTransaction,
        [$verified['eventEvidenceSha256']],
    ];
    foreach ($badEventCases as $name => $case) {
        $refused = RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier::verify(
            $case[0],
            $case[1],
            $eventSecret,
            $now,
            $case[2]
        );
        red_wompi_c2_assert(
            !$refused['valid']
                && $refused['orderMutationAuthorized'] === false
                && $refused['providerContact'] === false
                && $refused['paymentApplied'] === false,
            $name . ' event evidence fails closed without effects'
        );
    }

    echo 'Wompi C2 offline contract self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
