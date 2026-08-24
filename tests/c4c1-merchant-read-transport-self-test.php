<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
foreach ([
    'WompiMerchantContractRequestPlanner.php',
    'WompiMerchantContractResponseGate.php',
    'WompiMerchantContractTransport.php',
    'WompiMerchantContractTransportDouble.php',
    'WompiMerchantContractRetrieval.php',
] as $file) {
    require_once $projectDirectory . '/src/' . $file;
}

$assertions = 0;

function red_wompi_c4c1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4c1_plan(string $publicKey): array
{
    return RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan([
        'publicKeySettingPresent' => true,
        'publicKeySha256' => hash('sha256', $publicKey),
    ]);
}

try {
    $publicKey = 'pub_' . 'test_' . str_repeat('a', 32);
    $plan = red_wompi_c4c1_plan($publicKey);
    $double =
        new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double();
    $retrieved =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
            $plan,
            $publicKey,
            $double
        );
    red_wompi_c4c1_assert(
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::valid(
            $retrieved
        )
            && $retrieved['status'] === 'merchant_contracts_retrieved'
            && $double->callCount() === 1,
        'exact Sandbox public key invokes one sealed transport double'
    );
    red_wompi_c4c1_assert(
        $retrieved['planSha256'] === $plan['planSha256']
            && $retrieved['publicKeySha256'] === hash('sha256', $publicKey)
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $retrieved['requestSha256']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $retrieved['transportEvidenceSha256']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $double->requestSha256()
            ) === 1,
        'retrieval binds only hash identities to the single request'
    );
    red_wompi_c4c1_assert(
        $retrieved['contracts'] === [
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
        ]
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $retrieved['acceptanceTokenSha256']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $retrieved['personalAuthTokenSha256']
            ) === 1,
        'only two current contract links and token hashes survive containment'
    );
    red_wompi_c4c1_assert(
        $retrieved['executionPerformed']
            && !$retrieved['networkAccess']
            && !$retrieved['providerContact']
            && !$retrieved['responseBodyIncluded']
            && !$retrieved['responseHeadersIncluded']
            && !$retrieved['publicKeyIncluded']
            && !$retrieved['rawTokensReturned']
            && !$retrieved['providerMutation']
            && !$retrieved['transactionCreation']
            && !$retrieved['payment']
            && !$retrieved['eventRegistration']
            && !$retrieved['orderMutation']
            && !$retrieved['retryAuthorized'],
        'sealed-double result proves all real provider and business effects false'
    );
    $encoded = json_encode(
        $retrieved,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_wompi_c4c1_assert(
        !str_contains($encoded, $publicKey)
            && !str_contains($encoded, 'synthetic.end.user.')
            && !str_contains($encoded, 'synthetic.personal.auth.'),
        'result contains no raw public key, acceptance token, or response body'
    );

    $productionKey = 'pub_' . 'prod_' . str_repeat('b', 32);
    $productionDouble =
        new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double();
    $production =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
            red_wompi_c4c1_plan($productionKey),
            $productionKey,
            $productionDouble
        );
    red_wompi_c4c1_assert(
        $production['status'] === 'retrieval_refused'
            && $productionDouble->callCount() === 0,
        'production public key is refused before transport invocation'
    );
    $mismatchDouble =
        new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double();
    $mismatch =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
            $plan,
            'pub_' . 'test_' . str_repeat('c', 32),
            $mismatchDouble
        );
    red_wompi_c4c1_assert(
        $mismatch['status'] === 'retrieval_refused'
            && $mismatchDouble->callCount() === 0,
        'public-key hash mismatch is refused before transport invocation'
    );
    foreach (['fault', 'malformed'] as $mode) {
        $failedDouble =
            new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double(
                $mode
            );
        $failed =
            RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
                $plan,
                $publicKey,
                $failedDouble
            );
        red_wompi_c4c1_assert(
            !$failed['valid']
                && $failed['status'] === 'transport_indeterminate'
                && $failedDouble->callCount() === 1
                && !$failed['retryAuthorized'],
            $mode . ' transport is contained as one indeterminate attempt'
        );
    }

    foreach ([
        'WompiMerchantContractTransport.php',
        'WompiMerchantContractTransportDouble.php',
        'WompiMerchantContractRetrieval.php',
    ] as $file) {
        red_wompi_c4c1_assert(
            hash_equals(
                hash_file('sha256', $projectDirectory . '/src/' . $file),
                hash_file('sha256', $projectDirectory . '/package/' . $file)
            ),
            $file . ' package copy is byte-identical to reviewed source'
        );
    }
    $transportSource = (string) file_get_contents(
        $projectDirectory . '/src/WompiMerchantContractTransport.php'
    );
    red_wompi_c4c1_assert(
        substr_count($transportSource, 'curl_exec(') === 1
            && str_contains($transportSource, 'CURLOPT_HTTPGET => true')
            && str_contains($transportSource, 'CURLOPT_FOLLOWLOCATION => false')
            && str_contains($transportSource, 'CURLOPT_MAXREDIRS => 0')
            && str_contains($transportSource, 'CURLOPT_SSL_VERIFYPEER => true')
            && str_contains($transportSource, 'CURLOPT_SSL_VERIFYHOST => 2')
            && str_contains($transportSource, 'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS')
            && str_contains($transportSource, "CURLOPT_PROXY => ''")
            && str_contains($transportSource, 'CURLOPT_CONNECTTIMEOUT => 5')
            && str_contains($transportSource, 'CURLOPT_TIMEOUT => 10')
            && str_contains($transportSource, '> 16384')
            && str_contains($transportSource, '> $responseMaxBytes'),
        'real transport fixes one HTTPS GET with TLS, no redirect/proxy, and bounds'
    );
    foreach ([
        'CURLOPT_POST', 'CURLOPT_CUSTOMREQUEST', 'Authorization:',
        'prv_test_', 'prv_prod_', 'test_integrity_', 'test_events_',
        'production.wompi.co', 'sleep(', 'usleep(', 'file_put_contents(',
        'mysqli_', 'PDO',
    ] as $forbidden) {
        red_wompi_c4c1_assert(
            !str_contains($transportSource, $forbidden),
            $forbidden . ' is absent from the real read-only transport'
        );
    }
    $doubleSource = (string) file_get_contents(
        $projectDirectory . '/src/WompiMerchantContractTransportDouble.php'
    );
    red_wompi_c4c1_assert(
        !str_contains($doubleSource, 'curl_')
            && !str_contains($doubleSource, 'fsockopen(')
            && !str_contains($doubleSource, 'stream_socket_client('),
        'sealed double has no network primitive'
    );
    $retrievalSource = (string) file_get_contents(
        $projectDirectory . '/src/WompiMerchantContractRetrieval.php'
    );
    red_wompi_c4c1_assert(
        substr_count(
            $retrievalSource,
            "'https://sandbox.wompi.co/v1/merchants/'"
        ) === 1
            && !str_contains($retrievalSource, 'getenv(')
            && !str_contains($retrievalSource, '$_ENV')
            && !str_contains($retrievalSource, '$_SERVER')
            && !str_contains($retrievalSource, 'file_put_contents(')
            && !str_contains($retrievalSource, 'curl_')
            && !str_contains($retrievalSource, 'mysqli_')
            && !str_contains($retrievalSource, 'PDO'),
        'retrieval constructs one exact path and has no environment, storage, or transport primitive'
    );

    echo 'Wompi C4C1 merchant read transport self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
