<?php

declare(strict_types=1);

/**
 * Pure C4B1 plan for retrieving current Wompi acceptance contracts.
 *
 * The planner receives only a public-key hash and availability fact. It never
 * receives the public key, constructs the final path, or contacts Wompi.
 */
final class RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner
{
    public static function plan(array $setting): array
    {
        $result = self::result('contract_refused');
        if (array_keys($setting) !== [
                'publicKeySettingPresent', 'publicKeySha256',
            ]
            || ($setting['publicKeySettingPresent'] ?? null) !== true
            || !self::sha256($setting['publicKeySha256'] ?? null)
        ) {
            $result['errors'] = ['contract_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'merchant_contract_request_planned';
        $result['publicKeySettingPresent'] = true;
        $result['publicKeySha256'] = $setting['publicKeySha256'];
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
            && ($plan['status'] ?? null)
                === 'merchant_contract_request_planned'
            && ($plan['provider'] ?? null) === 'wompi'
            && ($plan['environment'] ?? null) === 'sandbox'
            && ($plan['operation'] ?? null)
                === 'merchant.acceptance-contracts.retrieve'
            && ($plan['targetHost'] ?? null) === 'sandbox.wompi.co'
            && ($plan['targetPathTemplate'] ?? null)
                === '/v1/merchants/{public_key}'
            && ($plan['httpMethod'] ?? null) === 'GET'
            && ($plan['responseMaxBytes'] ?? null) === 65536
            && ($plan['publicKeySettingPresent'] ?? null) === true
            && self::sha256($plan['publicKeySha256'] ?? null)
            && self::sha256($plan['planSha256'] ?? null)
            && hash_equals($plan['planSha256'], self::fingerprint($plan))
            && ($plan['wirePathConstructed'] ?? null) === false
            && ($plan['secretResolution'] ?? null) === false
            && ($plan['networkAccess'] ?? null) === false
            && ($plan['providerContact'] ?? null) === false
            && ($plan['providerMutation'] ?? null) === false
            && ($plan['payment'] ?? null) === false
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
            'environment' => 'sandbox',
            'operation' => 'merchant.acceptance-contracts.retrieve',
            'targetHost' => 'sandbox.wompi.co',
            'targetPathTemplate' => '/v1/merchants/{public_key}',
            'httpMethod' => 'GET',
            'responseMaxBytes' => 65536,
            'publicKeySettingPresent' => false,
            'publicKeySha256' => '',
            'planSha256' => '',
            'wirePathConstructed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
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
