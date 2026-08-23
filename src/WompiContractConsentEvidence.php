<?php

declare(strict_types=1);

/** Pure C4B2 evidence for explicit acceptance of both current contracts. */
final class RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence
{
    private const WINDOW_SECONDS = 900;

    public static function record(
        array $presentation,
        array $consent,
        int $nowEpoch
    ): array {
        $result = self::result('consent_refused');
        if (!RED_CMS_Store_Lite_Wompi_Contract_Consent_Presentation::valid(
            $presentation
        ) || !self::consentValid($presentation, $consent, $nowEpoch)) {
            $result['errors'] = ['consent_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'two_contract_consent_recorded';
        $result['orderId'] = $consent['orderId'];
        $result['subjectSha256'] = $consent['subjectSha256'];
        $result['presentationSha256'] = $consent['presentationSha256'];
        $result['contractsSha256'] = $consent['contractsSha256'];
        $result['acceptanceTokenSha256'] =
            $consent['acceptanceTokenSha256'];
        $result['personalAuthTokenSha256'] =
            $consent['personalAuthTokenSha256'];
        $result['consentNonceSha256'] = $consent['consentNonceSha256'];
        $result['acceptedAtEpoch'] = $consent['acceptedAtEpoch'];
        $result['consentValidUntilEpoch'] =
            $consent['acceptedAtEpoch'] + self::WINDOW_SECONDS;
        $result['endUserPolicyPresented'] = true;
        $result['personalDataAuthPresented'] = true;
        $result['endUserPolicyAccepted'] = true;
        $result['personalDataAuthAccepted'] = true;
        $result['consentReady'] = true;
        $result['consentEvidenceSha256'] = self::fingerprint($result);
        if (!self::valid($result, $nowEpoch)) {
            return self::result('consent_encoding_failed');
        }
        return $result;
    }

    public static function valid(array $result, int $atEpoch): bool
    {
        return array_keys($result) === array_keys(self::result())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null)
                === 'two_contract_consent_recorded'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['environment'] ?? null) === 'sandbox'
            && is_string($result['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $result['orderId']
            ) === 1
            && self::sha256($result['subjectSha256'] ?? null)
            && self::sha256($result['presentationSha256'] ?? null)
            && self::sha256($result['contractsSha256'] ?? null)
            && self::sha256($result['acceptanceTokenSha256'] ?? null)
            && self::sha256($result['personalAuthTokenSha256'] ?? null)
            && ($result['acceptanceTokenSha256'] ?? null)
                !== ($result['personalAuthTokenSha256'] ?? null)
            && self::sha256($result['consentNonceSha256'] ?? null)
            && self::epoch($result['acceptedAtEpoch'] ?? null)
            && self::epoch($result['consentValidUntilEpoch'] ?? null)
            && $result['consentValidUntilEpoch']
                === $result['acceptedAtEpoch'] + self::WINDOW_SECONDS
            && self::epoch($atEpoch)
            && $atEpoch >= $result['acceptedAtEpoch']
            && $atEpoch <= $result['consentValidUntilEpoch']
            && ($result['endUserPolicyPresented'] ?? null) === true
            && ($result['personalDataAuthPresented'] ?? null) === true
            && ($result['endUserPolicyAccepted'] ?? null) === true
            && ($result['personalDataAuthAccepted'] ?? null) === true
            && ($result['consentReady'] ?? null) === true
            && self::sha256($result['consentEvidenceSha256'] ?? null)
            && hash_equals(
                $result['consentEvidenceSha256'],
                self::fingerprint($result)
            )
            && ($result['contractLinksReturned'] ?? null) === false
            && ($result['rawTokensReturned'] ?? null) === false
            && ($result['wireRequestConstructed'] ?? null) === false
            && ($result['networkAccess'] ?? null) === false
            && ($result['providerContact'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['browserNavigation'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
            && ($result['errors'] ?? null) === [];
    }

    private static function consentValid(
        array $presentation,
        array $consent,
        int $nowEpoch
    ): bool {
        return array_keys($consent) === [
            'orderId',
            'subjectSha256',
            'presentationSha256',
            'contractsSha256',
            'acceptanceTokenSha256',
            'personalAuthTokenSha256',
            'consentNonceSha256',
            'endUserPolicyPresented',
            'personalDataAuthPresented',
            'endUserPolicyAccepted',
            'personalDataAuthAccepted',
            'acceptedAtEpoch',
        ]
            && is_string($consent['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $consent['orderId']
            ) === 1
            && self::sha256($consent['subjectSha256'] ?? null)
            && self::sha256($consent['presentationSha256'] ?? null)
            && hash_equals(
                $presentation['presentationSha256'],
                $consent['presentationSha256']
            )
            && self::sha256($consent['contractsSha256'] ?? null)
            && hash_equals(
                $presentation['contractsSha256'],
                $consent['contractsSha256']
            )
            && self::sha256($consent['acceptanceTokenSha256'] ?? null)
            && hash_equals(
                $presentation['acceptanceTokenSha256'],
                $consent['acceptanceTokenSha256']
            )
            && self::sha256($consent['personalAuthTokenSha256'] ?? null)
            && hash_equals(
                $presentation['personalAuthTokenSha256'],
                $consent['personalAuthTokenSha256']
            )
            && self::sha256($consent['consentNonceSha256'] ?? null)
            && ($consent['endUserPolicyPresented'] ?? null) === true
            && ($consent['personalDataAuthPresented'] ?? null) === true
            && ($consent['endUserPolicyAccepted'] ?? null) === true
            && ($consent['personalDataAuthAccepted'] ?? null) === true
            && self::epoch($consent['acceptedAtEpoch'] ?? null)
            && self::epoch($nowEpoch)
            && $consent['acceptedAtEpoch'] <= $nowEpoch
            && $consent['acceptedAtEpoch']
                >= $nowEpoch - self::WINDOW_SECONDS;
    }

    private static function result(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'environment' => 'sandbox',
            'orderId' => '',
            'subjectSha256' => '',
            'presentationSha256' => '',
            'contractsSha256' => '',
            'acceptanceTokenSha256' => '',
            'personalAuthTokenSha256' => '',
            'consentNonceSha256' => '',
            'acceptedAtEpoch' => 0,
            'consentValidUntilEpoch' => 0,
            'endUserPolicyPresented' => false,
            'personalDataAuthPresented' => false,
            'endUserPolicyAccepted' => false,
            'personalDataAuthAccepted' => false,
            'consentReady' => false,
            'consentEvidenceSha256' => '',
            'contractLinksReturned' => false,
            'rawTokensReturned' => false,
            'wireRequestConstructed' => false,
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

    private static function epoch($value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 4102444800;
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
        unset($result['valid'], $result['consentEvidenceSha256']);
        return self::hash($result);
    }
}

?>
