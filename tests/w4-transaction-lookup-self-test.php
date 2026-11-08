<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/WompiTransactionLookupTransport.php';

$assertions = 0;

function red_wompi_w4_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $transport = RED_CMS_Store_Lite_Wompi_Transaction_Lookup_Transport::class;
    red_wompi_w4_assert(
        $transport::urlValid(
            'https://sandbox.wompi.co/v1/transactions/1234-1794000000-49201'
        ),
        'the Sandbox transaction URL built by the coordinator is accepted'
    );
    // Every refusal returns before curl is touched, so this test never
    // contacts the network.
    foreach ([
        'production host' => 'https://production.wompi.co/v1/transactions/1234-1',
        'plain http' => 'http://sandbox.wompi.co/v1/transactions/1234-1',
        'other resource' => 'https://sandbox.wompi.co/v1/merchants/pub_test_x',
        'path traversal' => 'https://sandbox.wompi.co/v1/transactions/../merchants',
        'encoded slash' => 'https://sandbox.wompi.co/v1/transactions/1234%2F1',
        'query string' => 'https://sandbox.wompi.co/v1/transactions/1234-1?x=1',
        'look-alike host' => 'https://sandbox.wompi.co.evil.test/v1/transactions/1',
        'userinfo' => 'https://u@sandbox.wompi.co/v1/transactions/1234-1',
        'port' => 'https://sandbox.wompi.co:8443/v1/transactions/1234-1',
        'empty id' => 'https://sandbox.wompi.co/v1/transactions/',
        'trailing newline' => "https://sandbox.wompi.co/v1/transactions/1234-1\n",
        'oversized id' => 'https://sandbox.wompi.co/v1/transactions/' . str_repeat('a', 65),
    ] as $label => $url) {
        red_wompi_w4_assert(
            !$transport::urlValid($url) && $transport::get($url) === null,
            $label . ' is refused without a request'
        );
    }
    $source = (string) file_get_contents(
        dirname(__DIR__) . '/src/WompiTransactionLookupTransport.php'
    );
    red_wompi_w4_assert(
        substr_count($source, 'curl_exec(') === 1
            && str_contains($source, 'CURLOPT_FOLLOWLOCATION => false')
            && str_contains($source, 'CURLOPT_SSL_VERIFYPEER => true')
            && str_contains($source, 'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS')
            && !str_contains($source, 'Authorization')
            && !str_contains($source, 'CURLOPT_POST'),
        'one HTTPS GET with no redirects, no credential and no write'
    );
    red_wompi_w4_assert(
        file_get_contents(dirname(__DIR__) . '/package/WompiTransactionLookupTransport.php')
            === $source,
        'the package copy matches the source'
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

echo 'Wompi W4 transaction lookup self-test passed: ' . $assertions . " assertions.\n";
