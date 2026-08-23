<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory
    . '/src/WompiMerchantContractRequestPlanner.php';
require_once $projectDirectory
    . '/src/WompiMerchantContractResponseGate.php';

$assertions = 0;

function red_wompi_c4b1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4b1_setting(): array
{
    return [
        'publicKeySettingPresent' => true,
        'publicKeySha256' => str_repeat('1', 64),
    ];
}

function red_wompi_c4b1_response(): array
{
    return [
        'data' => [
            'presigned_acceptance' => [
                'acceptance_token' =>
                    'synthetic.end.user.' . str_repeat('a', 32),
                'permalink' =>
                    'https://contracts.wompi.co/synthetic-end-user.pdf',
                'type' => 'END_USER_POLICY',
            ],
            'presigned_personal_data_auth' => [
                'acceptance_token' =>
                    'synthetic.personal.auth.' . str_repeat('b', 32),
                'permalink' =>
                    'https://contracts.wompi.com/synthetic-personal-data.pdf',
                'type' => 'PERSONAL_DATA_AUTH',
            ],
        ],
    ];
}

try {
    $setting = red_wompi_c4b1_setting();
    $plan =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan(
            $setting
        );
    red_wompi_c4b1_assert(
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::valid(
            $plan
        ),
        'hashed public-key availability produces one valid pure plan'
    );
    red_wompi_c4b1_assert(
        $plan['provider'] === 'wompi'
            && $plan['environment'] === 'sandbox'
            && $plan['operation']
                === 'merchant.acceptance-contracts.retrieve'
            && $plan['targetHost'] === 'sandbox.wompi.co'
            && $plan['targetPathTemplate']
                === '/v1/merchants/{public_key}'
            && $plan['httpMethod'] === 'GET'
            && $plan['responseMaxBytes'] === 65536,
        'plan fixes the exact Sandbox merchant-contract GET boundary'
    );
    red_wompi_c4b1_assert(
        $plan['wirePathConstructed'] === false
            && $plan['secretResolution'] === false
            && $plan['networkAccess'] === false
            && $plan['providerContact'] === false
            && $plan['providerMutation'] === false
            && $plan['payment'] === false
            && $plan['browserNavigation'] === false
            && $plan['orderMutation'] === false
            && $plan['retryAuthorized'] === false,
        'plan keeps every wire, provider, payment, and business effect false'
    );
    red_wompi_c4b1_assert(
        $plan['publicKeySha256'] === $setting['publicKeySha256']
            && preg_match('/\A[a-f0-9]{64}\z/D', $plan['planSha256']) === 1
            && !array_key_exists('publicKey', $plan),
        'plan contains only the public-key hash and self-fingerprint'
    );

    $invalidSettings = [];
    $missing = $setting;
    $missing['publicKeySettingPresent'] = false;
    $invalidSettings['missing setting'] = $missing;
    $badHash = $setting;
    $badHash['publicKeySha256'] = str_repeat('z', 64);
    $invalidSettings['invalid hash'] = $badHash;
    $rawKey = $setting;
    $rawKey['publicKey'] = 'synthetic-public-value';
    $invalidSettings['raw public value'] = $rawKey;
    foreach ($invalidSettings as $name => $candidate) {
        $refused =
            RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan(
                $candidate
            );
        red_wompi_c4b1_assert(
            !$refused['valid']
                && $refused['status'] === 'contract_refused'
                && $refused['errors'] === ['contract_refused'],
            $name . ' is refused before a wire path can exist'
        );
    }

    $response = red_wompi_c4b1_response();
    $projection =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
            $plan,
            $response
        );
    red_wompi_c4b1_assert(
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::valid(
            $projection
        ),
        'exact synthetic current-contract response produces a valid projection'
    );
    red_wompi_c4b1_assert(
        $projection['contracts'] === [
            [
                'purpose' => 'end_user_policy',
                'providerType' => 'END_USER_POLICY',
                'permalink' =>
                    'https://contracts.wompi.co/synthetic-end-user.pdf',
            ],
            [
                'purpose' => 'personal_data_auth',
                'providerType' => 'PERSONAL_DATA_AUTH',
                'permalink' =>
                    'https://contracts.wompi.com/synthetic-personal-data.pdf',
            ],
        ],
        'projection preserves exactly two ordered HTTPS contract links'
    );
    red_wompi_c4b1_assert(
        $projection['acceptanceTokenSha256'] === hash(
            'sha256',
            $response['data']['presigned_acceptance']['acceptance_token']
        )
            && $projection['personalAuthTokenSha256'] === hash(
                'sha256',
                $response['data']['presigned_personal_data_auth'][
                    'acceptance_token'
                ]
            )
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $projection['contractsSha256']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $projection['responseEvidenceSha256']
            ) === 1,
        'projection returns only token hashes and bounded evidence hashes'
    );
    $encodedProjection = json_encode(
        $projection,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_wompi_c4b1_assert(
        !str_contains(
            $encodedProjection,
            $response['data']['presigned_acceptance']['acceptance_token']
        )
            && !str_contains(
                $encodedProjection,
                $response['data']['presigned_personal_data_auth'][
                    'acceptance_token'
                ]
            )
            && $projection['userConsentRequired'] === true
            && $projection['contractsPresented'] === false
            && $projection['rawTokensReturned'] === false
            && $projection['wireResponsePersisted'] === false,
        'raw tokens remain absent and later explicit consent remains required'
    );
    red_wompi_c4b1_assert(
        $projection['networkAccess'] === false
            && $projection['providerContact'] === false
            && $projection['providerMutation'] === false
            && $projection['payment'] === false
            && $projection['browserNavigation'] === false
            && $projection['orderMutation'] === false
            && $projection['retryAuthorized'] === false,
        'response projection keeps every external and business effect false'
    );

    $invalidResponses = [];
    $extra = $response;
    $extra['merchant_name'] = 'forbidden';
    $invalidResponses['extra top-level field'] = $extra;
    $missingContract = $response;
    unset($missingContract['data']['presigned_personal_data_auth']);
    $invalidResponses['missing personal contract'] = $missingContract;
    $wrongType = $response;
    $wrongType['data']['presigned_acceptance']['type'] = 'OTHER';
    $invalidResponses['wrong provider type'] = $wrongType;
    $shortToken = $response;
    $shortToken['data']['presigned_acceptance']['acceptance_token'] = 'short';
    $invalidResponses['short token'] = $shortToken;
    $spacedToken = $response;
    $spacedToken['data']['presigned_acceptance']['acceptance_token'] =
        'synthetic token with spaces';
    $invalidResponses['token whitespace'] = $spacedToken;
    $sameToken = $response;
    $sameToken['data']['presigned_personal_data_auth']['acceptance_token'] =
        $sameToken['data']['presigned_acceptance']['acceptance_token'];
    $invalidResponses['reused token'] = $sameToken;
    $httpLink = $response;
    $httpLink['data']['presigned_acceptance']['permalink'] =
        'http://contracts.wompi.co/synthetic-end-user.pdf';
    $invalidResponses['non-HTTPS contract'] = $httpLink;
    $credentialLink = $response;
    $credentialLink['data']['presigned_acceptance']['permalink'] =
        'https://user:pass@contracts.wompi.co/synthetic-end-user.pdf';
    $invalidResponses['credential-bearing contract URL'] = $credentialLink;
    $fragmentLink = $response;
    $fragmentLink['data']['presigned_acceptance']['permalink'] =
        'https://contracts.wompi.co/synthetic-end-user.pdf#fragment';
    $invalidResponses['fragment-bearing contract URL'] = $fragmentLink;
    $queryLink = $response;
    $queryLink['data']['presigned_acceptance']['permalink'] =
        'https://contracts.wompi.co/synthetic-end-user.pdf?token=forbidden';
    $invalidResponses['query-bearing contract URL'] = $queryLink;
    $foreignHost = $response;
    $foreignHost['data']['presigned_acceptance']['permalink'] =
        'https://contracts.example.test/end-user-policy.pdf';
    $invalidResponses['non-Wompi contract host'] = $foreignHost;
    $sameLink = $response;
    $sameLink['data']['presigned_personal_data_auth']['permalink'] =
        $sameLink['data']['presigned_acceptance']['permalink'];
    $invalidResponses['reused contract URL'] = $sameLink;
    foreach ($invalidResponses as $name => $candidate) {
        $refused =
            RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
                $plan,
                $candidate
            );
        red_wompi_c4b1_assert(
            !$refused['valid']
                && $refused['status'] === 'response_refused'
                && $refused['errors'] === ['response_refused']
                && $refused['rawTokensReturned'] === false
                && $refused['providerContact'] === false,
            $name . ' fails closed without returning token material'
        );
    }

    $changedPlan = $plan;
    $changedPlan['publicKeySha256'] = str_repeat('2', 64);
    $refusedChangedPlan =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
            $changedPlan,
            $response
        );
    red_wompi_c4b1_assert(
        !$refusedChangedPlan['valid']
            && $refusedChangedPlan['status'] === 'response_refused',
        'changed request plan is refused before response projection'
    );

    $changedProjection = $projection;
    $changedProjection['contracts'][0]['permalink'] =
        'https://contracts.wompi.co/changed.pdf';
    red_wompi_c4b1_assert(
        !RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::valid(
            $changedProjection
        ),
        'changed contract link invalidates the projection fingerprint'
    );

    foreach ([
        'WompiMerchantContractRequestPlanner.php',
        'WompiMerchantContractResponseGate.php',
    ] as $file) {
        red_wompi_c4b1_assert(
            hash_equals(
                hash_file('sha256', $projectDirectory . '/src/' . $file),
                hash_file('sha256', $projectDirectory . '/package/' . $file)
            ),
            $file . ' package copy is byte-identical to reviewed source'
        );
    }

    $source = (string) file_get_contents(
        $projectDirectory . '/src/WompiMerchantContractRequestPlanner.php'
    ) . (string) file_get_contents(
        $projectDirectory . '/src/WompiMerchantContractResponseGate.php'
    );
    red_wompi_c4b1_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|'
                . '\$_SESSION|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|'
                . '\bstream_socket_client\s*\(|\bfile_get_contents\s*\(|'
                . '\bPDO\b|\bmysqli_)/i',
            $source
        ) !== 1,
        'C4B1 classes have no request, environment, file, database, or network path'
    );

    echo 'Wompi C4B1 merchant-contract preflight self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
