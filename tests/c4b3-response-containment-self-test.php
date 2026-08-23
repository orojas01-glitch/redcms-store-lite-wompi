<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
foreach ([
    'WompiNequiRequestPlanner.php',
    'WompiNequiResponseGate.php',
    'WompiMerchantContractRequestPlanner.php',
    'WompiMerchantContractResponseGate.php',
    'WompiContractConsentPresentation.php',
    'WompiContractConsentEvidence.php',
    'WompiNequiTransientWireRequestBuilder.php',
    'WompiTransactionResponseContainment.php',
] as $file) {
    require_once $projectDirectory . '/src/' . $file;
}

$assertions = 0;

function red_wompi_c4b3_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4b3_fixture(): array
{
    $now = 1787443200;
    $transient = [
        'customer_email' => 'synthetic-buyer@example.test',
        'phone_number' => '3105550123',
        'acceptance_token' =>
            'synthetic.end.user.' . str_repeat('a', 32),
        'accept_personal_auth' =>
            'synthetic.personal.auth.' . str_repeat('b', 32),
        'private_key' => 'prv_' . 'test_' . str_repeat('c', 32),
        'integrity_secret' =>
            'test_' . 'integrity_' . str_repeat('d', 32),
    ];
    $merchantPlan =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan([
            'publicKeySettingPresent' => true,
            'publicKeySha256' => str_repeat('1', 64),
        ]);
    $projection =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
            $merchantPlan,
            [
                'data' => [
                    'presigned_acceptance' => [
                        'acceptance_token' =>
                            $transient['acceptance_token'],
                        'permalink' =>
                            'https://contracts.wompi.co/synthetic-end-user.pdf',
                        'type' => 'END_USER_POLICY',
                    ],
                    'presigned_personal_data_auth' => [
                        'acceptance_token' =>
                            $transient['accept_personal_auth'],
                        'permalink' =>
                            'https://contracts.wompi.com/synthetic-personal.pdf',
                        'type' => 'PERSONAL_DATA_AUTH',
                    ],
                ],
            ]
        );
    $presentation =
        RED_CMS_Store_Lite_Wompi_Contract_Consent_Presentation::present(
            $projection
        );
    $consent = RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::record(
        $presentation,
        [
            'orderId' => 'ord_0123456789abcdef0123456789abcdef',
            'subjectSha256' => str_repeat('4', 64),
            'presentationSha256' => $presentation['presentationSha256'],
            'contractsSha256' => $presentation['contractsSha256'],
            'acceptanceTokenSha256' =>
                $presentation['acceptanceTokenSha256'],
            'personalAuthTokenSha256' =>
                $presentation['personalAuthTokenSha256'],
            'consentNonceSha256' => str_repeat('5', 64),
            'endUserPolicyPresented' => true,
            'personalDataAuthPresented' => true,
            'endUserPolicyAccepted' => true,
            'personalDataAuthAccepted' => true,
            'acceptedAtEpoch' => $now - 10,
        ],
        $now
    );
    $order = [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('2', 64),
        'amountMinor' => 12500000,
        'currency' => 'COP',
        'idempotencySha256' => str_repeat('3', 64),
        'customerEmailSha256' => hash(
            'sha256',
            $transient['customer_email']
        ),
        'customerPhoneSha256' => hash(
            'sha256',
            $transient['phone_number']
        ),
    ];
    $plan = RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::plan(
        $order,
        [
            'privacyAccepted' => true,
            'personalDataAccepted' => true,
            'acceptanceTokenSha256' =>
                $consent['acceptanceTokenSha256'],
            'personalAuthTokenSha256' =>
                $consent['personalAuthTokenSha256'],
            'contractsSha256' => $consent['contractsSha256'],
        ],
        [
            'publicKeySettingPresent' => true,
            'privateKeyReferenceAvailable' => true,
            'integrityKeyReferenceAvailable' => true,
            'eventSecretReferenceAvailable' => true,
        ]
    );
    $wire =
        RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::build(
            $plan,
            $order,
            $consent,
            $transient,
            $now
        );
    return [$plan, $wire, $transient];
}

function red_wompi_c4b3_create_response(array $transient): array
{
    return [
        'data' => [
            'id' => '1234-5678-90ab-cdef',
            'reference' => 'ord_0123456789abcdef0123456789abcdef',
            'created_at' => '2026-08-23T12:00:00.000Z',
            'amount_in_cents' => 12500000,
            'currency' => 'COP',
            'customer_email' => $transient['customer_email'],
            'payment_method_type' => 'NEQUI',
            'status' => 'PENDING',
            'status_message' => 'Synthetic pending response',
            'merchant' => [
                'id' => 42,
                'name' => 'Synthetic merchant',
            ],
            'payment_method' => [
                'type' => 'NEQUI',
                'phone_number' => $transient['phone_number'],
            ],
        ],
    ];
}

function red_wompi_c4b3_lookup_response(string $status): array
{
    return [
        'data' => [
            'id' => '1234-5678-90ab-cdef',
            'reference' => 'ord_0123456789abcdef0123456789abcdef',
            'status' => $status,
            'amount_in_cents' => 12500000,
            'currency' => 'COP',
            'payment_method_type' => 'NEQUI',
            'status_message' => 'Synthetic ' . strtolower($status),
        ],
    ];
}

try {
    [$plan, $wire, $transient] = red_wompi_c4b3_fixture();
    red_wompi_c4b3_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan),
        'fixture has a valid C2 transaction plan'
    );
    red_wompi_c4b3_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::valid(
            $wire
        ),
        'fixture has valid C4B2 wire evidence'
    );

    $response = red_wompi_c4b3_create_response($transient);
    $created =
        RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::create(
            $plan,
            $wire,
            201,
            $response
        );
    red_wompi_c4b3_assert(
        RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validCreate(
            $created
        ),
        'documented synthetic HTTP 201 create response is contained'
    );
    red_wompi_c4b3_assert(
        $created['providerReference'] === '1234-5678-90ab-cdef'
            && $created['reference'] === $plan['orderId']
            && $created['amountMinor'] === $plan['amountMinor']
            && $created['transactionStatus'] === 'PENDING',
        'create projection retains only transaction identity and state'
    );
    red_wompi_c4b3_assert(
        $created['discardedFieldNames'] === [
            'created_at',
            'customer_email',
            'merchant',
            'payment_method',
            'status_message',
        ],
        'create projection records only sorted names of discarded fields'
    );
    red_wompi_c4b3_assert(
        $created['initiation']['value']['state'] === 'pending'
            && $created['initiation']['value']['customerAction']
                === 'approve_in_provider_app',
        'existing C1 response gate still controls initiation semantics'
    );
    red_wompi_c4b3_assert(
        $created['paymentVerified'] === false
            && $created['eventAgreement'] === false
            && $created['paymentApplied'] === false
            && $created['orderMutationAuthorized'] === false
            && $created['providerMutation'] === false
            && $created['retryAuthorized'] === false,
        'create containment grants no payment, mutation, or retry authority'
    );
    $encodedCreated = json_encode(
        $created,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    foreach ([
        'email' => $transient['customer_email'],
        'phone' => $transient['phone_number'],
        'merchant name' => 'Synthetic merchant',
        'status message' => 'Synthetic pending response',
    ] as $name => $forbiddenValue) {
        red_wompi_c4b3_assert(
            !str_contains($encodedCreated, $forbiddenValue),
            'contained create evidence excludes raw ' . $name
        );
    }

    $createCases = [];
    $createCases['wrong HTTP status'] = [$plan, $wire, 200, $response];
    $missing = $response;
    unset($missing['data']['id']);
    $createCases['missing required field'] = [$plan, $wire, 201, $missing];
    $extra = $response;
    $extra['data']['unexpected'] = 'value';
    $createCases['unknown field'] = [$plan, $wire, 201, $extra];
    foreach ([
        'reference' => 'ord_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'amount_in_cents' => 12500001,
        'currency' => 'USD',
        'payment_method_type' => 'CARD',
        'status' => 'APPROVED',
    ] as $field => $value) {
        $changed = $response;
        $changed['data'][$field] = $value;
        $createCases['changed ' . $field] = [$plan, $wire, 201, $changed];
    }
    $invalidEmail = $response;
    $invalidEmail['data']['customer_email'] = "bad\n@example.test";
    $createCases['invalid discarded email'] = [
        $plan, $wire, 201, $invalidEmail,
    ];
    $nested = $response;
    $nested['data']['merchant']['nested'] = ['not' => 'flat'];
    $createCases['nested discarded merchant'] = [$plan, $wire, 201, $nested];
    $oversized = $response;
    $oversized['data']['status_message'] = str_repeat('x', 66000);
    $createCases['oversized response'] = [$plan, $wire, 201, $oversized];
    $changedWire = $wire;
    $changedWire['wireRequestSha256'] = str_repeat('9', 64);
    $createCases['tampered wire evidence'] = [
        $plan, $changedWire, 201, $response,
    ];
    foreach ($createCases as $name => $case) {
        $refused =
            RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::create(
                $case[0],
                $case[1],
                $case[2],
                $case[3]
            );
        red_wompi_c4b3_assert(
            !$refused['valid']
                && $refused['status'] === 'create_response_refused'
                && $refused['errors'] === ['create_response_refused']
                && $refused['providerContact'] === false
                && $refused['orderMutationAuthorized'] === false,
            $name . ' is refused without effects'
        );
    }

    foreach ([
        'PENDING' => [false, 'pending'],
        'APPROVED' => [true, 'paid'],
        'DECLINED' => [true, 'failed'],
        'ERROR' => [true, 'failed'],
    ] as $status => $expected) {
        $lookup =
            RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::lookup(
                $created,
                200,
                red_wompi_c4b3_lookup_response($status)
            );
        red_wompi_c4b3_assert(
            RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validLookup(
                $lookup
            ),
            $status . ' lookup response is contained'
        );
        red_wompi_c4b3_assert(
            $lookup['final'] === $expected[0]
                && $lookup['proposedOutcome'] === $expected[1],
            $status . ' maps only to the expected proposed outcome'
        );
        red_wompi_c4b3_assert(
            $lookup['paymentVerified'] === false
                && $lookup['eventAgreement'] === false
                && $lookup['paymentApplied'] === false
                && $lookup['orderMutationAuthorized'] === false,
            $status . ' lookup alone cannot verify or mutate an order'
        );
    }

    $lookupBase = red_wompi_c4b3_lookup_response('APPROVED');
    $lookupCases = [];
    $lookupCases['wrong HTTP status'] = [$created, 201, $lookupBase];
    foreach ([
        'id' => 'different-transaction',
        'reference' => 'ord_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'amount_in_cents' => 12500001,
        'currency' => 'USD',
        'payment_method_type' => 'CARD',
        'status' => 'VOIDED',
    ] as $field => $value) {
        $changed = $lookupBase;
        $changed['data'][$field] = $value;
        $lookupCases['changed ' . $field] = [$created, 200, $changed];
    }
    $unknownLookup = $lookupBase;
    $unknownLookup['data']['raw'] = 'forbidden';
    $lookupCases['unknown response field'] = [
        $created, 200, $unknownLookup,
    ];
    $changedCreate = $created;
    $changedCreate['amountMinor']++;
    $lookupCases['tampered create evidence'] = [
        $changedCreate, 200, $lookupBase,
    ];
    foreach ($lookupCases as $name => $case) {
        $refused =
            RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::lookup(
                $case[0],
                $case[1],
                $case[2]
            );
        red_wompi_c4b3_assert(
            !$refused['valid']
                && $refused['status'] === 'lookup_response_refused'
                && $refused['errors'] === ['lookup_response_refused']
                && $refused['paymentVerified'] === false
                && $refused['orderMutationAuthorized'] === false,
            $name . ' lookup is refused without effects'
        );
    }

    $changedCreate = $created;
    $changedCreate['responseProjectionSha256'] = str_repeat('8', 64);
    red_wompi_c4b3_assert(
        !RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validCreate(
            $changedCreate
        ),
        'changed create projection evidence fails its self-fingerprint'
    );
    $lookup =
        RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::lookup(
            $created,
            200,
            $lookupBase
        );
    $changedLookup = $lookup;
    $changedLookup['proposedOutcome'] = 'pending';
    red_wompi_c4b3_assert(
        !RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validLookup(
            $changedLookup
        ),
        'changed lookup outcome fails semantic and fingerprint validation'
    );
    red_wompi_c4b3_assert(
        hash_equals(
            hash_file(
                'sha256',
                $projectDirectory
                    . '/src/WompiTransactionResponseContainment.php'
            ),
            hash_file(
                'sha256',
                $projectDirectory
                    . '/package/WompiTransactionResponseContainment.php'
            )
        ),
        'response containment package copy is byte-identical to source'
    );
    $source = (string) file_get_contents(
        $projectDirectory . '/src/WompiTransactionResponseContainment.php'
    );
    red_wompi_c4b3_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|'
                . '\$_SESSION|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|'
                . '\bstream_socket_client\s*\(|\bPDO\b|\bmysqli_|'
                . 'red_addon_(?:runtime_)?secret|\bheader\s*\(|'
                . '\bhttp_response_code\s*\()/i',
            $source
        ) !== 1,
        'C4B3 class has no request, environment, secret, database, network, or response path'
    );

    echo 'Wompi C4B3 response containment self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
