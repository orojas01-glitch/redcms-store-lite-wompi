<?php

declare(strict_types=1);

/**
 * Pure C2 Wompi/Nequi Sandbox request-evidence planner.
 *
 * It receives hashes and availability facts only. It cannot construct a wire
 * request, resolve a key, load customer data, or contact Wompi.
 */
final class RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner
{
    public static function plan(
        array $order,
        array $acceptance,
        array $settings
    ): array {
        $result = self::result('contract_refused');
        if (!self::orderValid($order)
            || !self::acceptanceValid($acceptance)
            || !self::settingsValid($settings)
        ) {
            $result['errors'] = ['contract_refused'];
            return $result;
        }

        $requestEvidenceSha256 = self::hash([
            'schema' => 1,
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'targetHost' => 'sandbox.wompi.co',
            'targetPath' => '/v1/transactions',
            'httpMethod' => 'POST',
            'order' => $order,
            'acceptance' => $acceptance,
            'settings' => $settings,
        ]);
        $acceptanceEvidenceSha256 = self::hash($acceptance);
        if (!self::sha256($requestEvidenceSha256)
            || !self::sha256($acceptanceEvidenceSha256)
        ) {
            $result['status'] = 'evidence_encoding_failed';
            $result['errors'] = ['evidence_encoding_failed'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'offline_request_planned';
        $result['orderId'] = $order['orderId'];
        $result['amountMinor'] = $order['amountMinor'];
        $result['currency'] = 'COP';
        $result['requestEvidenceSha256'] = $requestEvidenceSha256;
        $result['acceptanceEvidenceSha256'] = $acceptanceEvidenceSha256;
        $result['publicKeySettingPresent'] = true;
        $result['privateKeyReferenceAvailable'] = true;
        $result['integrityKeyReferenceAvailable'] = true;
        $result['eventSecretReferenceAvailable'] = true;
        $result['planSha256'] = self::fingerprint($result);
        if (!self::sha256($result['planSha256'])) {
            return self::result('plan_encoding_failed');
        }
        return $result;
    }

    public static function valid(array $plan): bool
    {
        return array_keys($plan) === array_keys(self::result())
            && ($plan['valid'] ?? null) === true
            && ($plan['status'] ?? null) === 'offline_request_planned'
            && ($plan['provider'] ?? null) === 'wompi'
            && ($plan['method'] ?? null) === 'nequi'
            && ($plan['environment'] ?? null) === 'sandbox'
            && ($plan['initiationMode'] ?? null)
                === 'out_of_band_confirmation'
            && ($plan['targetHost'] ?? null) === 'sandbox.wompi.co'
            && ($plan['targetPath'] ?? null) === '/v1/transactions'
            && ($plan['httpMethod'] ?? null) === 'POST'
            && is_string($plan['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $plan['orderId']
            ) === 1
            && is_int($plan['amountMinor'] ?? null)
            && $plan['amountMinor'] >= 100
            && $plan['amountMinor'] <= 999999999999
            && ($plan['currency'] ?? null) === 'COP'
            && self::sha256($plan['requestEvidenceSha256'] ?? null)
            && self::sha256($plan['acceptanceEvidenceSha256'] ?? null)
            && self::sha256($plan['planSha256'] ?? null)
            && hash_equals($plan['planSha256'], self::fingerprint($plan))
            && ($plan['publicKeySettingPresent'] ?? null) === true
            && ($plan['privateKeyReferenceAvailable'] ?? null) === true
            && ($plan['integrityKeyReferenceAvailable'] ?? null) === true
            && ($plan['eventSecretReferenceAvailable'] ?? null) === true
            && ($plan['wireRequestConstructed'] ?? null) === false
            && ($plan['secretResolution'] ?? null) === false
            && ($plan['networkAccess'] ?? null) === false
            && ($plan['providerContact'] ?? null) === false
            && ($plan['providerMutation'] ?? null) === false
            && ($plan['payment'] ?? null) === false
            && ($plan['webhook'] ?? null) === false
            && ($plan['browserNavigation'] ?? null) === false
            && ($plan['orderMutation'] ?? null) === false
            && ($plan['retryAuthorized'] ?? null) === false
            && ($plan['errors'] ?? null) === [];
    }

    private static function result(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'initiationMode' => 'out_of_band_confirmation',
            'targetHost' => 'sandbox.wompi.co',
            'targetPath' => '/v1/transactions',
            'httpMethod' => 'POST',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'requestEvidenceSha256' => '',
            'acceptanceEvidenceSha256' => '',
            'planSha256' => '',
            'publicKeySettingPresent' => false,
            'privateKeyReferenceAvailable' => false,
            'integrityKeyReferenceAvailable' => false,
            'eventSecretReferenceAvailable' => false,
            'wireRequestConstructed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }

    private static function orderValid(array $order): bool
    {
        return array_keys($order) === [
            'orderId', 'orderSnapshotSha256', 'amountMinor', 'currency',
            'idempotencySha256', 'customerEmailSha256',
            'customerPhoneSha256',
        ]
            && is_string($order['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $order['orderId']
            ) === 1
            && self::sha256($order['orderSnapshotSha256'] ?? null)
            && is_int($order['amountMinor'] ?? null)
            && $order['amountMinor'] >= 100
            && $order['amountMinor'] <= 999999999999
            && ($order['currency'] ?? null) === 'COP'
            && self::sha256($order['idempotencySha256'] ?? null)
            && self::sha256($order['customerEmailSha256'] ?? null)
            && self::sha256($order['customerPhoneSha256'] ?? null);
    }

    private static function acceptanceValid(array $acceptance): bool
    {
        return array_keys($acceptance) === [
            'privacyAccepted', 'personalDataAccepted',
            'acceptanceTokenSha256', 'personalAuthTokenSha256',
            'contractsSha256',
        ]
            && ($acceptance['privacyAccepted'] ?? null) === true
            && ($acceptance['personalDataAccepted'] ?? null) === true
            && self::sha256($acceptance['acceptanceTokenSha256'] ?? null)
            && self::sha256($acceptance['personalAuthTokenSha256'] ?? null)
            && self::sha256($acceptance['contractsSha256'] ?? null);
    }

    private static function settingsValid(array $settings): bool
    {
        return array_keys($settings) === [
            'publicKeySettingPresent', 'privateKeyReferenceAvailable',
            'integrityKeyReferenceAvailable',
            'eventSecretReferenceAvailable',
        ]
            && ($settings['publicKeySettingPresent'] ?? null) === true
            && ($settings['privateKeyReferenceAvailable'] ?? null) === true
            && ($settings['integrityKeyReferenceAvailable'] ?? null) === true
            && ($settings['eventSecretReferenceAvailable'] ?? null) === true;
    }

    private static function sha256($value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function hash(array $value): string
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return hash('sha256', $encoded);
    }

    private static function fingerprint(array $plan): string
    {
        unset($plan['valid'], $plan['planSha256']);
        return self::hash($plan);
    }
}

?>
