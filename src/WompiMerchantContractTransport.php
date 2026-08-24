<?php

declare(strict_types=1);

interface RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport
{
    public function get(string $url, int $responseMaxBytes): array;
}

/** Exact one-shot HTTPS GET transport for the Sandbox merchant endpoint. */
final class RED_CMS_Store_Lite_Wompi_Merchant_Contract_Curl_Transport
    implements RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport
{
    public function get(string $url, int $responseMaxBytes): array
    {
        $result = self::result('transport_refused');
        if (!self::urlValid($url)
            || $responseMaxBytes !== 65536
            || !function_exists('curl_init')
            || !defined('CURLPROTO_HTTPS')
        ) {
            $result['errors'] = ['transport_refused'];
            return $result;
        }

        $body = '';
        $overflow = false;
        $headerBytes = 0;
        $headerOverflow = false;
        $handle = curl_init();
        if ($handle === false) {
            $result['errors'] = ['transport_unavailable'];
            return $result;
        }
        $configured = curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Connection: close',
            ],
            CURLOPT_USERAGENT => 'RED-CMS-Wompi/0.1.5',
            CURLOPT_HEADERFUNCTION => static function (
                $curl,
                $header
            ) use (&$headerBytes, &$headerOverflow): int {
                if (!is_string($header)) {
                    return 0;
                }
                $length = strlen($header);
                if ($headerBytes + $length > 16384) {
                    $headerOverflow = true;
                    return 0;
                }
                $headerBytes += $length;
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function (
                $curl,
                $chunk
            ) use (&$body, &$overflow, $responseMaxBytes): int {
                if (!is_string($chunk)) {
                    return 0;
                }
                $length = strlen($chunk);
                if (strlen($body) + $length > $responseMaxBytes) {
                    $overflow = true;
                    return 0;
                }
                $body .= $chunk;
                return $length;
            },
        ]);
        if (!$configured) {
            curl_close($handle);
            $result['errors'] = ['transport_configuration_failed'];
            return $result;
        }

        $result['networkAccess'] = true;
        $executed = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $result['providerContact'] = $statusCode > 0;
        $result['statusCode'] = $statusCode;
        $result['responseBytes'] = strlen($body);
        curl_close($handle);

        if ($overflow || $headerOverflow) {
            $body = '';
            $result['responseBytes'] = 0;
            $result['status'] = 'response_too_large';
            $result['errors'] = ['response_too_large'];
            return $result;
        }
        if ($executed !== true) {
            $body = '';
            $result['responseBytes'] = 0;
            $result['status'] = 'transport_failed';
            $result['errors'] = ['transport_failed'];
            return $result;
        }
        if ($statusCode !== 200
            || $body === ''
            || strlen($body) > $responseMaxBytes
        ) {
            $body = '';
            $result['responseBytes'] = 0;
            $result['status'] = 'response_refused';
            $result['errors'] = ['response_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'response_received';
        $result['responseBody'] = $body;
        $result['errors'] = [];
        return $result;
    }

    private static function urlValid(string $url): bool
    {
        return strlen($url) <= 256
            && preg_match(
                '/\Ahttps:\/\/sandbox\.wompi\.co\/v1\/merchants\/'
                    . 'pub_test_[A-Za-z0-9]{16,128}\z/D',
                $url
            ) === 1;
    }

    private static function result(string $status): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'statusCode' => 0,
            'responseBody' => '',
            'responseBytes' => 0,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'transactionCreation' => false,
            'payment' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }
}

?>
