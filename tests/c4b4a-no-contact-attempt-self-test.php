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
    'WompiNoContactAttemptContract.php',
] as $file) {
    require_once $projectDirectory . '/src/' . $file;
}

$assertions = 0;

function red_wompi_c4b4a_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4b4a_fixture(): array
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
    return [$now, $transient, $plan, $wire];
}

function red_wompi_c4b4a_scope(int $now): array
{
    return [
        'clientScopeSha256' => str_repeat('6', 64),
        'databaseSha256' => str_repeat('7', 64),
        'actorSubjectSha256' => str_repeat('8', 64),
        'secretAvailabilitySha256' => str_repeat('9', 64),
        'authorizationNonceSha256' => str_repeat('a', 64),
        'issuedAtEpoch' => $now - 5,
        'expiresAtEpoch' => $now + 895,
        'ownerAuthorityRevalidated' => true,
        'orderAuthorityRevalidated' => true,
        'packageEnabled' => true,
        'storeEnabled' => true,
        'oneAttemptConfirmed' => true,
        'noRetryConfirmed' => true,
        'networkDisabledConfirmed' => true,
        'providerContactDenied' => true,
        'providerMutationDenied' => true,
        'orderMutationDenied' => true,
    ];
}

function red_wompi_c4b4a_claim_input(
    array $authorization,
    int $now
): array {
    return [
        'authorizationSha256' => $authorization['authorizationSha256'],
        'claimNonceSha256' => str_repeat('b', 64),
        'claimedAtEpoch' => $now,
        'attemptNumber' => 1,
        'priorClaimEvidenceSha256' => [],
        'oneAttemptConfirmed' => true,
        'noRetryConfirmed' => true,
        'durableClaimRequired' => true,
    ];
}

function red_wompi_c4b4a_create_response(array $transient): array
{
    return [
        'data' => [
            'id' => '1234-5678-90ab-cdef',
            'reference' => 'ord_0123456789abcdef0123456789abcdef',
            'amount_in_cents' => 12500000,
            'currency' => 'COP',
            'customer_email' => $transient['customer_email'],
            'payment_method_type' => 'NEQUI',
            'status' => 'PENDING',
            'payment_method' => [
                'type' => 'NEQUI',
                'phone_number' => $transient['phone_number'],
            ],
        ],
    ];
}

function red_wompi_c4b4a_lookup_response(string $status): array
{
    return [
        'data' => [
            'id' => '1234-5678-90ab-cdef',
            'reference' => 'ord_0123456789abcdef0123456789abcdef',
            'status' => $status,
            'amount_in_cents' => 12500000,
            'currency' => 'COP',
            'payment_method_type' => 'NEQUI',
        ],
    ];
}

try {
    [$now, $transient, $plan, $wire] = red_wompi_c4b4a_fixture();
    $scope = red_wompi_c4b4a_scope($now);
    $authorization =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::authorize(
            $plan,
            $wire,
            $scope,
            $now
        );
    red_wompi_c4b4a_assert(
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validAuthorization(
            $authorization,
            $now
        ),
        'valid plan, wire, authority hashes, and 15-minute window authorize only a no-contact attempt'
    );
    red_wompi_c4b4a_assert(
        $authorization['operation']
            === 'checkout.create-sandbox-no-contact'
            && $authorization['transportMode'] === 'sealed_double_only'
            && $authorization['maximumAttempts'] === 1
            && $authorization['noContactAttemptAuthorized'] === true,
        'authorization fixes the exact sealed-double operation and one attempt'
    );
    red_wompi_c4b4a_assert(
        $authorization['providerContactAuthorized'] === false
            && $authorization['providerMutationAuthorized'] === false
            && $authorization['orderMutationAuthorized'] === false
            && $authorization['authorizationPersisted'] === false
            && $authorization['claimPersisted'] === false
            && $authorization['durableClaimRequired'] === true,
        'authorization grants no provider/order effect and admits durable state is still required'
    );
    red_wompi_c4b4a_assert(
        $authorization['planSha256'] === $plan['planSha256']
            && $authorization['wireRequestSha256']
                === $wire['wireRequestSha256']
            && $authorization['wireEvidenceSha256']
                === $wire['buildEvidenceSha256'],
        'authorization binds exact C2 plan and C4B2 wire identities'
    );

    foreach ([
        'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
        'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
        'noRetryConfirmed', 'networkDisabledConfirmed',
        'providerContactDenied', 'providerMutationDenied',
        'orderMutationDenied',
    ] as $key) {
        $changed = $scope;
        $changed[$key] = false;
        $refused =
            RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::authorize(
                $plan,
                $wire,
                $changed,
                $now
            );
        red_wompi_c4b4a_assert(
            !$refused['valid']
                && $refused['errors'] === ['authorization_refused'],
            $key . ' false refuses authorization'
        );
    }

    $authorizationCases = [];
    foreach ([
        'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
        'secretAvailabilitySha256', 'authorizationNonceSha256',
    ] as $key) {
        $changed = $scope;
        $changed[$key] = str_repeat('z', 64);
        $authorizationCases['invalid ' . $key] = [$plan, $wire, $changed, $now];
    }
    $future = $scope;
    $future['issuedAtEpoch'] = $now + 1;
    $authorizationCases['future issue time'] = [$plan, $wire, $future, $now];
    $expired = $scope;
    $expired['expiresAtEpoch'] = $now;
    $authorizationCases['expired window'] = [$plan, $wire, $expired, $now];
    $long = $scope;
    $long['issuedAtEpoch'] = $now - 6;
    $authorizationCases['overlong window'] = [$plan, $wire, $long, $now];
    $extra = $scope;
    $extra['retry'] = true;
    $authorizationCases['extra scope field'] = [$plan, $wire, $extra, $now];
    $changedPlan = $plan;
    $changedPlan['amountMinor']++;
    $authorizationCases['tampered plan'] = [
        $changedPlan, $wire, $scope, $now,
    ];
    $changedWire = $wire;
    $changedWire['wireRequestSha256'] = str_repeat('c', 64);
    $authorizationCases['tampered wire'] = [
        $plan, $changedWire, $scope, $now,
    ];
    foreach ($authorizationCases as $name => $case) {
        $refused =
            RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::authorize(
                $case[0],
                $case[1],
                $case[2],
                $case[3]
            );
        red_wompi_c4b4a_assert(
            !$refused['valid']
                && $refused['errors'] === ['authorization_refused']
                && $refused['providerContactAuthorized'] === false,
            $name . ' refuses authorization without effects'
        );
    }
    red_wompi_c4b4a_assert(
        !RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validAuthorization(
            $authorization,
            $authorization['expiresAtEpoch']
        ),
        'authorization expires at the exact upper bound'
    );

    $claimInput = red_wompi_c4b4a_claim_input($authorization, $now);
    $claim =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::prepareClaim(
            $authorization,
            $claimInput,
            $now
        );
    red_wompi_c4b4a_assert(
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validClaim(
            $claim,
            $authorization
        ),
        'first exact claim input produces bounded claim preparation evidence'
    );
    red_wompi_c4b4a_assert(
        $claim['attemptNumber'] === 1
            && $claim['maximumAttempts'] === 1
            && $claim['remainingAttempts'] === 0
            && $claim['priorClaimCount'] === 0
            && $claim['noRetryConfirmed'] === true,
        'claim preparation consumes the entire one-attempt allowance'
    );
    red_wompi_c4b4a_assert(
        $claim['durableClaimRequired'] === true
            && $claim['claimPersisted'] === false
            && $claim['replayProtectionActive'] === false
            && $claim['executionAuthorized'] === false,
        'pure claim evidence cannot impersonate durable replay protection or execution authority'
    );

    $claimCases = [];
    $wrongAuth = $claimInput;
    $wrongAuth['authorizationSha256'] = str_repeat('c', 64);
    $claimCases['changed authorization hash'] = $wrongAuth;
    $sameNonce = $claimInput;
    $sameNonce['claimNonceSha256'] =
        $authorization['authorizationNonceSha256'];
    $claimCases['reused authorization nonce'] = $sameNonce;
    $attemptTwo = $claimInput;
    $attemptTwo['attemptNumber'] = 2;
    $claimCases['second attempt'] = $attemptTwo;
    $prior = $claimInput;
    $prior['priorClaimEvidenceSha256'] = [$claim['claimSha256']];
    $claimCases['prior claim present'] = $prior;
    foreach ([
        'oneAttemptConfirmed', 'noRetryConfirmed', 'durableClaimRequired',
    ] as $key) {
        $changed = $claimInput;
        $changed[$key] = false;
        $claimCases[$key . ' false'] = $changed;
    }
    $extraClaim = $claimInput;
    $extraClaim['execute'] = true;
    $claimCases['extra claim field'] = $extraClaim;
    foreach ($claimCases as $name => $candidate) {
        $refused =
            RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::prepareClaim(
                $authorization,
                $candidate,
                $now
            );
        red_wompi_c4b4a_assert(
            !$refused['valid']
                && $refused['errors'] === ['claim_refused']
                && $refused['executionAuthorized'] === false,
            $name . ' refuses claim preparation'
        );
    }

    $claimOnly =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::projectState(
            $authorization,
            $claim
        );
    red_wompi_c4b4a_assert(
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validState(
            $claimOnly,
            $authorization,
            $claim
        )
            && $claimOnly['attemptState'] === 'claim_prepared'
            && $claimOnly['providerReference'] === '',
        'claim-only projection records no provider observation'
    );

    $create =
        RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::create(
            $plan,
            $wire,
            201,
            red_wompi_c4b4a_create_response($transient)
        );
    $pending =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::projectState(
            $authorization,
            $claim,
            $create
        );
    red_wompi_c4b4a_assert(
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validState(
            $pending,
            $authorization,
            $claim,
            $create
        )
            && $pending['attemptState'] === 'pending_observed'
            && $pending['transactionStatus'] === 'PENDING'
            && $pending['proposedOutcome'] === 'pending',
        'contained create evidence projects only a pending observation'
    );

    foreach ([
        'PENDING' => ['pending_observed', 'pending', false],
        'APPROVED' => ['approved_observed', 'paid', true],
        'DECLINED' => ['failed_observed', 'failed', true],
        'ERROR' => ['failed_observed', 'failed', true],
    ] as $status => $expected) {
        $lookup =
            RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::lookup(
                $create,
                200,
                red_wompi_c4b4a_lookup_response($status)
            );
        $state =
            RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::projectState(
                $authorization,
                $claim,
                $create,
                $lookup
            );
        red_wompi_c4b4a_assert(
            RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validState(
                $state,
                $authorization,
                $claim,
                $create,
                $lookup
            ),
            $status . ' contained lookup projects valid state evidence'
        );
        red_wompi_c4b4a_assert(
            $state['attemptState'] === $expected[0]
                && $state['proposedOutcome'] === $expected[1]
                && $state['finalObservation'] === $expected[2]
                && $state['paymentVerified'] === false
                && $state['eventAgreement'] === false
                && $state['orderMutationAuthorized'] === false,
            $status . ' remains observation-only with no payment or order authority'
        );
    }

    $lookupOnly =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::projectState(
            $authorization,
            $claim,
            [],
            RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::lookup(
                $create,
                200,
                red_wompi_c4b4a_lookup_response('APPROVED')
            )
        );
    red_wompi_c4b4a_assert(
        !$lookupOnly['valid'] && $lookupOnly['errors'] === ['state_refused'],
        'lookup cannot project state without its exact create evidence'
    );
    $changedCreate = $create;
    $changedCreate['wireRequestSha256'] = str_repeat('d', 64);
    $refusedState =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::projectState(
            $authorization,
            $claim,
            $changedCreate
        );
    red_wompi_c4b4a_assert(
        !$refusedState['valid']
            && $refusedState['errors'] === ['state_refused'],
        'changed create evidence cannot enter the attempt state'
    );
    $changedState = $pending;
    $changedState['attemptState'] = 'approved_observed';
    red_wompi_c4b4a_assert(
        !RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validState(
            $changedState,
            $authorization,
            $claim,
            $create
        ),
        'changed state semantics fail fingerprint and recomputation'
    );

    red_wompi_c4b4a_assert(
        hash_equals(
            hash_file(
                'sha256',
                $projectDirectory . '/src/WompiNoContactAttemptContract.php'
            ),
            hash_file(
                'sha256',
                $projectDirectory
                    . '/package/WompiNoContactAttemptContract.php'
            )
        ),
        'no-contact attempt package copy is byte-identical to source'
    );
    $source = (string) file_get_contents(
        $projectDirectory . '/src/WompiNoContactAttemptContract.php'
    );
    red_wompi_c4b4a_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|'
                . '\$_SESSION|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|'
                . '\bstream_socket_client\s*\(|\bPDO\b|\bmysqli_|'
                . 'red_addon_(?:runtime_)?secret|\bheader\s*\(|'
                . '\bhttp_response_code\s*\()/i',
            $source
        ) !== 1,
        'C4B4A contract has no request, environment, secret, database, network, or response path'
    );

    echo 'Wompi C4B4A no-contact attempt self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
