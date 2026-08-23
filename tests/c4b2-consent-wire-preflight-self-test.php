<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
foreach ([
    'WompiNequiRequestPlanner.php',
    'WompiMerchantContractRequestPlanner.php',
    'WompiMerchantContractResponseGate.php',
    'WompiContractConsentPresentation.php',
    'WompiContractConsentEvidence.php',
    'WompiNequiTransientWireRequestBuilder.php',
] as $file) {
    require_once $projectDirectory . '/src/' . $file;
}

$assertions = 0;

function red_wompi_c4b2_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4b2_hash(array $value): string
{
    return hash(
        'sha256',
        json_encode(
            $value,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        )
    );
}

function red_wompi_c4b2_transient(): array
{
    return [
        'customer_email' => 'synthetic-buyer@example.test',
        'phone_number' => '3105550123',
        'acceptance_token' =>
            'synthetic.end.user.' . str_repeat('a', 32),
        'accept_personal_auth' =>
            'synthetic.personal.auth.' . str_repeat('b', 32),
        'private_key' =>
            'prv_' . 'test_' . str_repeat('c', 32),
        'integrity_secret' =>
            'test_' . 'integrity_' . str_repeat('d', 32),
    ];
}

function red_wompi_c4b2_contract_projection(array $transient): array
{
    $merchantPlan =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan([
            'publicKeySettingPresent' => true,
            'publicKeySha256' => str_repeat('1', 64),
        ]);
    return RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
        $merchantPlan,
        [
            'data' => [
                'presigned_acceptance' => [
                    'acceptance_token' => $transient['acceptance_token'],
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
}

function red_wompi_c4b2_order(array $transient): array
{
    return [
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
}

function red_wompi_c4b2_consent_input(
    array $presentation,
    int $acceptedAt
): array {
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'subjectSha256' => str_repeat('4', 64),
        'presentationSha256' => $presentation['presentationSha256'],
        'contractsSha256' => $presentation['contractsSha256'],
        'acceptanceTokenSha256' => $presentation['acceptanceTokenSha256'],
        'personalAuthTokenSha256' =>
            $presentation['personalAuthTokenSha256'],
        'consentNonceSha256' => str_repeat('5', 64),
        'endUserPolicyPresented' => true,
        'personalDataAuthPresented' => true,
        'endUserPolicyAccepted' => true,
        'personalDataAuthAccepted' => true,
        'acceptedAtEpoch' => $acceptedAt,
    ];
}

function red_wompi_c4b2_transaction_plan(
    array $order,
    array $consent
): array {
    return RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::plan(
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
}

try {
    $now = 1787443200;
    $transient = red_wompi_c4b2_transient();
    $projection = red_wompi_c4b2_contract_projection($transient);
    red_wompi_c4b2_assert(
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::valid(
            $projection
        ),
        'exact synthetic merchant response supplies current contract evidence'
    );

    $presentation =
        RED_CMS_Store_Lite_Wompi_Contract_Consent_Presentation::present(
            $projection
        );
    red_wompi_c4b2_assert(
        RED_CMS_Store_Lite_Wompi_Contract_Consent_Presentation::valid(
            $presentation
        ),
        'exactly two current contract links and controls are presentation-ready'
    );
    red_wompi_c4b2_assert(
        $presentation['contracts'] === $projection['contracts']
            && $presentation['controls'] === [
                [
                    'name' => 'end_user_policy_accepted',
                    'purpose' => 'end_user_policy',
                    'required' => true,
                ],
                [
                    'name' => 'personal_data_auth_accepted',
                    'purpose' => 'personal_data_auth',
                    'required' => true,
                ],
            ]
            && $presentation['rawTokensReturned'] === false
            && $presentation['htmlReturned'] === false
            && $presentation['browserRendered'] === false
            && $presentation['consentRecorded'] === false,
        'presentation exposes only links and two required control models'
    );

    $consentInput = red_wompi_c4b2_consent_input($presentation, $now - 10);
    $consent = RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::record(
        $presentation,
        $consentInput,
        $now
    );
    red_wompi_c4b2_assert(
        RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::valid(
            $consent,
            $now
        ),
        'two presented and accepted current contracts produce valid evidence'
    );
    red_wompi_c4b2_assert(
        $consent['endUserPolicyPresented'] === true
            && $consent['personalDataAuthPresented'] === true
            && $consent['endUserPolicyAccepted'] === true
            && $consent['personalDataAuthAccepted'] === true
            && $consent['consentReady'] === true
            && $consent['consentValidUntilEpoch']
                === $consent['acceptedAtEpoch'] + 900,
        'consent binds both explicit acceptances to a 15-minute window'
    );
    red_wompi_c4b2_assert(
        $consent['orderId'] === $consentInput['orderId']
            && $consent['subjectSha256'] === $consentInput['subjectSha256']
            && $consent['presentationSha256']
                === $presentation['presentationSha256']
            && $consent['contractsSha256']
                === $projection['contractsSha256']
            && $consent['acceptanceTokenSha256']
                === $projection['acceptanceTokenSha256']
            && $consent['personalAuthTokenSha256']
                === $projection['personalAuthTokenSha256'],
        'consent binds order, subject, contracts, and both token hashes'
    );
    $encodedConsent = json_encode(
        $consent,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_wompi_c4b2_assert(
        $consent['contractLinksReturned'] === false
            && $consent['rawTokensReturned'] === false
            && !str_contains($encodedConsent, 'https://')
            && !str_contains(
                $encodedConsent,
                $transient['acceptance_token']
            )
            && !str_contains(
                $encodedConsent,
                $transient['accept_personal_auth']
            ),
        'consent evidence returns neither links nor raw tokens'
    );

    $consentCases = [];
    $policyNotPresented = $consentInput;
    $policyNotPresented['endUserPolicyPresented'] = false;
    $consentCases['policy not presented'] = $policyNotPresented;
    $personalNotPresented = $consentInput;
    $personalNotPresented['personalDataAuthPresented'] = false;
    $consentCases['personal authorization not presented'] =
        $personalNotPresented;
    $policyNotAccepted = $consentInput;
    $policyNotAccepted['endUserPolicyAccepted'] = false;
    $consentCases['policy not accepted'] = $policyNotAccepted;
    $personalNotAccepted = $consentInput;
    $personalNotAccepted['personalDataAuthAccepted'] = false;
    $consentCases['personal authorization not accepted'] =
        $personalNotAccepted;
    $wrongContracts = $consentInput;
    $wrongContracts['contractsSha256'] = str_repeat('6', 64);
    $consentCases['contract hash changed'] = $wrongContracts;
    $wrongToken = $consentInput;
    $wrongToken['acceptanceTokenSha256'] = str_repeat('7', 64);
    $consentCases['token hash changed'] = $wrongToken;
    $future = $consentInput;
    $future['acceptedAtEpoch'] = $now + 1;
    $consentCases['future acceptance'] = $future;
    $expired = $consentInput;
    $expired['acceptedAtEpoch'] = $now - 901;
    $consentCases['expired acceptance'] = $expired;
    $extraConsent = $consentInput;
    $extraConsent['rawToken'] = 'forbidden';
    $consentCases['extra consent field'] = $extraConsent;
    foreach ($consentCases as $name => $candidate) {
        $refused = RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::record(
            $presentation,
            $candidate,
            $now
        );
        red_wompi_c4b2_assert(
            !$refused['valid']
                && $refused['status'] === 'consent_refused'
                && $refused['errors'] === ['consent_refused']
                && $refused['wireRequestConstructed'] === false,
            $name . ' fails before wire construction'
        );
    }

    $changedPresentation = $presentation;
    $changedPresentation['controls'][0]['required'] = false;
    $refusedPresentation =
        RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::record(
            $changedPresentation,
            $consentInput,
            $now
        );
    red_wompi_c4b2_assert(
        !$refusedPresentation['valid']
            && $refusedPresentation['status'] === 'consent_refused',
        'changed presentation model cannot produce consent evidence'
    );

    $changedConsent = $consent;
    $changedConsent['subjectSha256'] = str_repeat('8', 64);
    red_wompi_c4b2_assert(
        !RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::valid(
            $changedConsent,
            $now
        ),
        'changed consent evidence fails its self-fingerprint'
    );
    red_wompi_c4b2_assert(
        !RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::valid(
            $consent,
            $consent['consentValidUntilEpoch'] + 1
        ),
        'recorded consent refuses use after its exact validity window'
    );

    $order = red_wompi_c4b2_order($transient);
    $plan = red_wompi_c4b2_transaction_plan($order, $consent);
    red_wompi_c4b2_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan),
        'existing C2 transaction plan binds the same consent and order hashes'
    );
    $built =
        RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::build(
            $plan,
            $order,
            $consent,
            $transient,
            $now
        );
    red_wompi_c4b2_assert(
        RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::valid(
            $built
        ),
        'exact synthetic values produce one redacted transient wire proof'
    );
    red_wompi_c4b2_assert(
        $built['targetHost'] === 'sandbox.wompi.co'
            && $built['targetPath'] === '/v1/transactions'
            && $built['httpMethod'] === 'POST'
            && $built['currency'] === 'COP'
            && $built['wireFieldNames'] === [
                'acceptance_token',
                'accept_personal_auth',
                'amount_in_cents',
                'currency',
                'signature',
                'customer_email',
                'reference',
                'payment_method',
            ]
            && $built['paymentMethodFieldNames'] === [
                'type', 'phone_number',
            ],
        'wire proof fixes exact Sandbox POST and body field order'
    );

    $integrityInput = $order['orderId']
        . (string) $order['amountMinor']
        . 'COP'
        . $transient['integrity_secret'];
    $signature = hash('sha256', $integrityInput);
    $body = [
        'acceptance_token' => $transient['acceptance_token'],
        'accept_personal_auth' => $transient['accept_personal_auth'],
        'amount_in_cents' => $order['amountMinor'],
        'currency' => 'COP',
        'signature' => $signature,
        'customer_email' => $transient['customer_email'],
        'reference' => $order['orderId'],
        'payment_method' => [
            'type' => 'NEQUI',
            'phone_number' => $transient['phone_number'],
        ],
    ];
    $authorization = 'Bearer ' . $transient['private_key'];
    $request = [
        'httpMethod' => 'POST',
        'targetHost' => 'sandbox.wompi.co',
        'targetPath' => '/v1/transactions',
        'headers' => [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
        ],
        'body' => $body,
    ];
    red_wompi_c4b2_assert(
        $built['integrityInputEvidenceSha256'] === hash(
            'sha256',
            "wompi-integrity-input-evidence-v1\0" . $integrityInput
        )
            && $built['integritySignatureSha256']
                === hash('sha256', $signature)
            && $built['authorizationHeaderSha256']
                === hash('sha256', $authorization)
            && $built['wireBodySha256'] === red_wompi_c4b2_hash($body)
            && $built['wireRequestSha256'] === red_wompi_c4b2_hash($request),
        'proof hashes exact Wompi signature, authorization, body, and request'
    );
    red_wompi_c4b2_assert(
        !array_key_exists('customerEmailSha256', $built)
            && !array_key_exists('customerPhoneSha256', $built)
            && $built['acceptanceTokenSha256']
                === hash('sha256', $transient['acceptance_token'])
            && $built['personalAuthTokenSha256']
                === hash('sha256', $transient['accept_personal_auth'])
            && $built['consentEvidenceSha256']
                === $consent['consentEvidenceSha256'],
        'wire proof omits personal hashes and binds token/consent evidence'
    );
    red_wompi_c4b2_assert(
        $built['wireRequestConstructed'] === true
            && $built['wireRequestReturned'] === false
            && $built['wireRequestPersisted'] === false
            && $built['credentialsReturned'] === false
            && $built['personalDataReturned'] === false
            && $built['rawTokensReturned'] === false
            && $built['integritySignatureReturned'] === false
            && $built['secretResolution'] === false
            && $built['networkAccess'] === false
            && $built['providerContact'] === false
            && $built['providerMutation'] === false
            && $built['payment'] === false
            && $built['browserNavigation'] === false
            && $built['orderMutation'] === false
            && $built['retryAuthorized'] === false,
        'construction discards raw wire values and keeps every effect false'
    );
    $encodedBuild = json_encode(
        $built,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    foreach ([
        'email' => $transient['customer_email'],
        'phone' => $transient['phone_number'],
        'acceptance token' => $transient['acceptance_token'],
        'personal auth token' => $transient['accept_personal_auth'],
        'private key' => $transient['private_key'],
        'integrity secret' => $transient['integrity_secret'],
        'authorization header' => $authorization,
        'integrity signature' => $signature,
    ] as $name => $forbiddenValue) {
        red_wompi_c4b2_assert(
            !str_contains($encodedBuild, $forbiddenValue),
            'redacted wire proof excludes raw ' . $name
        );
    }

    $wireCases = [];
    $wrongEmail = $transient;
    $wrongEmail['customer_email'] = 'other@example.test';
    $wireCases['email hash mismatch'] = [$plan, $order, $consent, $wrongEmail, $now];
    $badEmail = $transient;
    $badEmail['customer_email'] = "bad\n@example.test";
    $wireCases['malformed email'] = [$plan, $order, $consent, $badEmail, $now];
    $wrongPhone = $transient;
    $wrongPhone['phone_number'] = '3105550124';
    $wireCases['phone hash mismatch'] = [$plan, $order, $consent, $wrongPhone, $now];
    $badPhone = $transient;
    $badPhone['phone_number'] = '2105550123';
    $wireCases['non-Colombian phone'] = [$plan, $order, $consent, $badPhone, $now];
    $wrongTokenValue = $transient;
    $wrongTokenValue['acceptance_token'] =
        'synthetic.changed.' . str_repeat('e', 32);
    $wireCases['token hash mismatch'] = [
        $plan, $order, $consent, $wrongTokenValue, $now,
    ];
    $productionPrivate = $transient;
    $productionPrivate['private_key'] =
        'prv_' . 'prod_' . str_repeat('f', 32);
    $wireCases['production private key'] = [
        $plan, $order, $consent, $productionPrivate, $now,
    ];
    $productionIntegrity = $transient;
    $productionIntegrity['integrity_secret'] =
        'prod_' . 'integrity_' . str_repeat('g', 32);
    $wireCases['production integrity secret'] = [
        $plan, $order, $consent, $productionIntegrity, $now,
    ];
    $extraTransient = $transient;
    $extraTransient['retry'] = true;
    $wireCases['extra transient field'] = [
        $plan, $order, $consent, $extraTransient, $now,
    ];
    $changedPlan = $plan;
    $changedPlan['amountMinor']++;
    $wireCases['changed plan'] = [
        $changedPlan, $order, $consent, $transient, $now,
    ];
    $changedOrder = $order;
    $changedOrder['customerPhoneSha256'] = str_repeat('9', 64);
    $wireCases['changed order evidence'] = [
        $plan, $changedOrder, $consent, $transient, $now,
    ];
    $wireCases['expired consent use'] = [
        $plan,
        $order,
        $consent,
        $transient,
        $consent['consentValidUntilEpoch'] + 1,
    ];
    foreach ($wireCases as $name => $case) {
        $refused =
            RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::build(
                $case[0],
                $case[1],
                $case[2],
                $case[3],
                $case[4]
            );
        red_wompi_c4b2_assert(
            !$refused['valid']
                && $refused['status'] === 'wire_request_refused'
                && $refused['errors'] === ['wire_request_refused']
                && $refused['wireRequestConstructed'] === false
                && $refused['providerContact'] === false,
            $name . ' fails before a reusable request can exist'
        );
    }

    $changedBuild = $built;
    $changedBuild['wireBodySha256'] = str_repeat('a', 64);
    red_wompi_c4b2_assert(
        !RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::valid(
            $changedBuild
        ),
        'changed wire evidence fails its self-fingerprint'
    );

    foreach ([
        'WompiContractConsentPresentation.php',
        'WompiContractConsentEvidence.php',
        'WompiNequiTransientWireRequestBuilder.php',
    ] as $file) {
        red_wompi_c4b2_assert(
            hash_equals(
                hash_file('sha256', $projectDirectory . '/src/' . $file),
                hash_file('sha256', $projectDirectory . '/package/' . $file)
            ),
            $file . ' package copy is byte-identical to reviewed source'
        );
    }

    $source = (string) file_get_contents(
        $projectDirectory . '/src/WompiContractConsentPresentation.php'
    ) . (string) file_get_contents(
        $projectDirectory . '/src/WompiContractConsentEvidence.php'
    ) . (string) file_get_contents(
        $projectDirectory . '/src/WompiNequiTransientWireRequestBuilder.php'
    );
    red_wompi_c4b2_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|'
                . '\$_SESSION|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|'
                . '\bstream_socket_client\s*\(|\bfile_get_contents\s*\(|'
                . '\bPDO\b|\bmysqli_|red_addon_(?:runtime_)?secret|'
                . '\bheader\s*\(|\bhttp_response_code\s*\()/i',
            $source
        ) !== 1,
        'C4B2 classes have no request, environment, file, secret, database, network, or response path'
    );

    echo 'Wompi C4B2 consent/wire preflight self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
