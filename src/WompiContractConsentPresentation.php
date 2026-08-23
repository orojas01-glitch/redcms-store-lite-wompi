<?php

declare(strict_types=1);

/** Pure C4B2 model for presenting exactly two current Wompi contracts. */
final class RED_CMS_Store_Lite_Wompi_Contract_Consent_Presentation
{
    public static function present(array $contractProjection): array
    {
        $result = self::result('presentation_refused');
        if (!RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::valid(
            $contractProjection
        )) {
            $result['errors'] = ['presentation_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'two_contract_presentation_ready';
        $result['contracts'] = $contractProjection['contracts'];
        $result['controls'] = [
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
        ];
        $result['contractsSha256'] =
            $contractProjection['contractsSha256'];
        $result['acceptanceTokenSha256'] =
            $contractProjection['acceptanceTokenSha256'];
        $result['personalAuthTokenSha256'] =
            $contractProjection['personalAuthTokenSha256'];
        $result['presentationSha256'] = self::fingerprint($result);
        if (!self::valid($result)) {
            return self::result('presentation_encoding_failed');
        }
        return $result;
    }

    public static function valid(array $result): bool
    {
        return array_keys($result) === array_keys(self::result())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null)
                === 'two_contract_presentation_ready'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['environment'] ?? null) === 'sandbox'
            && self::contractsValid($result['contracts'] ?? null)
            && ($result['controls'] ?? null) === [
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
            && self::sha256($result['contractsSha256'] ?? null)
            && hash_equals(
                $result['contractsSha256'],
                self::hash($result['contracts'])
            )
            && self::sha256($result['acceptanceTokenSha256'] ?? null)
            && self::sha256($result['personalAuthTokenSha256'] ?? null)
            && ($result['acceptanceTokenSha256'] ?? null)
                !== ($result['personalAuthTokenSha256'] ?? null)
            && self::sha256($result['presentationSha256'] ?? null)
            && hash_equals(
                $result['presentationSha256'],
                self::fingerprint($result)
            )
            && ($result['rawTokensReturned'] ?? null) === false
            && ($result['htmlReturned'] ?? null) === false
            && ($result['browserRendered'] ?? null) === false
            && ($result['consentRecorded'] ?? null) === false
            && ($result['networkAccess'] ?? null) === false
            && ($result['providerContact'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['errors'] ?? null) === [];
    }

    private static function contractsValid($contracts): bool
    {
        return is_array($contracts)
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
            && $contracts[0]['permalink'] !== $contracts[1]['permalink'];
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
            && self::wompiHttpsUrl($contract['permalink'] ?? null);
    }

    private static function wompiHttpsUrl($value): bool
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
            && ($host === 'wompi.co'
                || str_ends_with($host, '.wompi.co')
                || $host === 'wompi.com'
                || str_ends_with($host, '.wompi.com'))
            && !array_key_exists('user', $parts)
            && !array_key_exists('pass', $parts)
            && (!array_key_exists('port', $parts)
                || ($parts['port'] ?? null) === 443)
            && !array_key_exists('query', $parts)
            && !array_key_exists('fragment', $parts)
            && is_string($parts['path'] ?? null)
            && str_starts_with($parts['path'], '/');
    }

    private static function result(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'environment' => 'sandbox',
            'contracts' => null,
            'controls' => [],
            'contractsSha256' => '',
            'acceptanceTokenSha256' => '',
            'personalAuthTokenSha256' => '',
            'presentationSha256' => '',
            'rawTokensReturned' => false,
            'htmlReturned' => false,
            'browserRendered' => false,
            'consentRecorded' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'orderMutation' => false,
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
        unset($result['valid'], $result['presentationSha256']);
        return self::hash($result);
    }
}

?>
