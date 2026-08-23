<?php

declare(strict_types=1);

/** Pure C4B1 projection of current Wompi contract links and token hashes. */
final class RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate
{
    public static function project(array $plan, array $response): array
    {
        $result = self::result('response_refused');
        if (!RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::valid(
            $plan
        ) || !self::responseValid($response)) {
            $result['errors'] = ['response_refused'];
            return $result;
        }

        $acceptance = $response['data']['presigned_acceptance'];
        $personal = $response['data']['presigned_personal_data_auth'];
        $contracts = [
            [
                'purpose' => 'end_user_policy',
                'providerType' => 'END_USER_POLICY',
                'permalink' => $acceptance['permalink'],
            ],
            [
                'purpose' => 'personal_data_auth',
                'providerType' => 'PERSONAL_DATA_AUTH',
                'permalink' => $personal['permalink'],
            ],
        ];
        $result['valid'] = true;
        $result['status'] = 'merchant_contract_response_projected';
        $result['contracts'] = $contracts;
        $result['acceptanceTokenSha256'] = hash(
            'sha256',
            $acceptance['acceptance_token']
        );
        $result['personalAuthTokenSha256'] = hash(
            'sha256',
            $personal['acceptance_token']
        );
        $result['contractsSha256'] = self::hash($contracts);
        $result['responseEvidenceSha256'] = self::hash($response);
        $result['projectionSha256'] = self::fingerprint($result);
        if (!self::valid($result)) {
            return self::result('projection_encoding_failed');
        }
        return $result;
    }

    public static function valid(array $result): bool
    {
        $contracts = $result['contracts'] ?? null;
        return array_keys($result) === array_keys(self::result())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null)
                === 'merchant_contract_response_projected'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['environment'] ?? null) === 'sandbox'
            && is_array($contracts)
            && array_keys($contracts) === [0, 1]
            && self::contractValid(
                $contracts[0] ?? null,
                'end_user_policy',
                'END_USER_POLICY'
            )
            && self::contractValid(
                $contracts[1] ?? null,
                'personal_data_auth',
                'PERSONAL_DATA_AUTH'
            )
            && ($contracts[0]['permalink'] ?? null)
                !== ($contracts[1]['permalink'] ?? null)
            && self::sha256($result['acceptanceTokenSha256'] ?? null)
            && self::sha256($result['personalAuthTokenSha256'] ?? null)
            && ($result['acceptanceTokenSha256'] ?? null)
                !== ($result['personalAuthTokenSha256'] ?? null)
            && self::sha256($result['contractsSha256'] ?? null)
            && hash_equals(
                $result['contractsSha256'],
                self::hash($contracts)
            )
            && self::sha256($result['responseEvidenceSha256'] ?? null)
            && self::sha256($result['projectionSha256'] ?? null)
            && hash_equals(
                $result['projectionSha256'],
                self::fingerprint($result)
            )
            && ($result['userConsentRequired'] ?? null) === true
            && ($result['contractsPresented'] ?? null) === false
            && ($result['rawTokensReturned'] ?? null) === false
            && ($result['wireResponsePersisted'] ?? null) === false
            && ($result['networkAccess'] ?? null) === false
            && ($result['providerContact'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['browserNavigation'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
            && ($result['errors'] ?? null) === [];
    }

    private static function responseValid(array $response): bool
    {
        if (array_keys($response) !== ['data']
            || !is_array($response['data'] ?? null)
            || array_keys($response['data']) !== [
                'presigned_acceptance',
                'presigned_personal_data_auth',
            ]
        ) {
            return false;
        }
        $acceptance = $response['data']['presigned_acceptance'];
        $personal = $response['data']['presigned_personal_data_auth'];
        return self::providerContractValid($acceptance, 'END_USER_POLICY')
            && self::providerContractValid($personal, 'PERSONAL_DATA_AUTH')
            && !hash_equals(
                $acceptance['acceptance_token'],
                $personal['acceptance_token']
            )
            && $acceptance['permalink'] !== $personal['permalink'];
    }

    private static function providerContractValid($contract, string $type): bool
    {
        return is_array($contract)
            && array_keys($contract) === [
                'acceptance_token', 'permalink', 'type',
            ]
            && self::opaqueToken($contract['acceptance_token'] ?? null)
            && self::httpsUrl($contract['permalink'] ?? null)
            && ($contract['type'] ?? null) === $type;
    }

    private static function contractValid(
        $contract,
        string $purpose,
        string $providerType
    ): bool {
        return is_array($contract)
            && array_keys($contract) === [
                'purpose', 'providerType', 'permalink',
            ]
            && ($contract['purpose'] ?? null) === $purpose
            && ($contract['providerType'] ?? null) === $providerType
            && self::httpsUrl($contract['permalink'] ?? null);
    }

    private static function opaqueToken($value): bool
    {
        return is_string($value)
            && strlen($value) >= 20
            && strlen($value) <= 4096
            && preg_match('/\A[A-Za-z0-9._~-]+\z/D', $value) === 1;
    }

    private static function httpsUrl($value): bool
    {
        if (!is_string($value)
            || strlen($value) > 2048
            || filter_var($value, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }
        $parts = parse_url($value);
        $host = is_array($parts) && is_string($parts['host'] ?? null)
            ? strtolower($parts['host'])
            : '';
        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && $host !== ''
            && filter_var(
                $host,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) !== false
            && self::wompiContractHost($host)
            && !array_key_exists('user', $parts)
            && !array_key_exists('pass', $parts)
            && (!array_key_exists('port', $parts)
                || ($parts['port'] ?? null) === 443)
            && !array_key_exists('query', $parts)
            && !array_key_exists('fragment', $parts)
            && is_string($parts['path'] ?? null)
            && str_starts_with($parts['path'], '/');
    }

    private static function wompiContractHost(string $host): bool
    {
        return $host === 'wompi.co'
            || str_ends_with($host, '.wompi.co')
            || $host === 'wompi.com'
            || str_ends_with($host, '.wompi.com');
    }

    private static function result(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'environment' => 'sandbox',
            'contracts' => null,
            'acceptanceTokenSha256' => '',
            'personalAuthTokenSha256' => '',
            'contractsSha256' => '',
            'responseEvidenceSha256' => '',
            'projectionSha256' => '',
            'userConsentRequired' => true,
            'contractsPresented' => false,
            'rawTokensReturned' => false,
            'wireResponsePersisted' => false,
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

    private static function fingerprint(array $result): string
    {
        unset($result['valid'], $result['projectionSha256']);
        return self::hash($result);
    }
}

?>
