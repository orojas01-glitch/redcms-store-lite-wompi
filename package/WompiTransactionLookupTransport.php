<?php

declare(strict_types=1);

/**
 * W4 bounded server-side confirmation GET for Wompi Web Checkout events.
 *
 * Fetches exactly one public transaction resource,
 * GET https://sandbox.wompi.co/v1/transactions/{id}, and returns its raw body
 * for RED_CMS_Store_Lite_Wompi_Event_Verifier::projectLookup(), or null on any
 * refusal or failure. The endpoint needs no key, so no credential is sent.
 * Only the Sandbox host is accepted until production is authorized; the
 * coordinator then treats null as a transient failure and Wompi retries.
 */
final class RED_CMS_Store_Lite_Wompi_Transaction_Lookup_Transport
{
    public const RESPONSE_MAX_BYTES = 65536;
    private const HEADER_MAX_BYTES = 16384;

    public static function urlValid(string $url): bool
    {
        return strlen($url) <= 160
            && preg_match(
                '/\Ahttps:\/\/sandbox\.wompi\.co\/v1\/transactions\/'
                    . '[A-Za-z0-9][A-Za-z0-9-]{0,63}\z/D',
                $url
            ) === 1;
    }

    public static function get(string $url): ?string
    {
        if (!self::urlValid($url)
            || !function_exists('curl_init')
            || !defined('CURLPROTO_HTTPS')
        ) {
            return null;
        }
        $body = '';
        $overflow = false;
        $headerBytes = 0;
        $headerOverflow = false;
        $handle = curl_init();
        if ($handle === false) {
            return null;
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
            CURLOPT_USERAGENT => 'RED-CMS-Wompi/0.4.0',
            CURLOPT_HEADERFUNCTION => static function (
                $curl,
                $header
            ) use (&$headerBytes, &$headerOverflow): int {
                if (!is_string($header)) {
                    return 0;
                }
                $length = strlen($header);
                if ($headerBytes + $length > self::HEADER_MAX_BYTES) {
                    $headerOverflow = true;
                    return 0;
                }
                $headerBytes += $length;
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function (
                $curl,
                $chunk
            ) use (&$body, &$overflow): int {
                if (!is_string($chunk)) {
                    return 0;
                }
                $length = strlen($chunk);
                if (strlen($body) + $length > self::RESPONSE_MAX_BYTES) {
                    $overflow = true;
                    return 0;
                }
                $body .= $chunk;
                return $length;
            },
        ]);
        if (!$configured) {
            curl_close($handle);
            return null;
        }
        $executed = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($executed !== true
            || $overflow
            || $headerOverflow
            || $statusCode !== 200
            || $body === ''
        ) {
            return null;
        }
        return $body;
    }
}

?>
