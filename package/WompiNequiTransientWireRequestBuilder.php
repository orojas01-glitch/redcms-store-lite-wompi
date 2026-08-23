<?php

declare(strict_types=1);

/**
 * Pure C4B2 transient Wompi/Nequi wire preflight.
 *
 * Raw synthetic values exist only inside build(). The method returns hashes
 * and closed facts, never a reusable body, header, credential, token, email,
 * phone number, integrity signature, or transport callback.
 */
final class RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder
{
    public static function build(
        array $plan,
        array $orderEvidence,
        array $consentEvidence,
        array $transient,
        int $nowEpoch
    ): array {
        $result = self::result('wire_request_refused');
        if (!self::inputsValid(
            $plan,
            $orderEvidence,
            $consentEvidence,
            $transient,
            $nowEpoch
        )) {
            $result['errors'] = ['wire_request_refused'];
            return $result;
        }

        $integrityInput = $plan['orderId']
            . (string) $plan['amountMinor']
            . 'COP'
            . $transient['integrity_secret'];
        $integritySignature = hash('sha256', $integrityInput);
        $body = [
            'acceptance_token' => $transient['acceptance_token'],
            'accept_personal_auth' => $transient['accept_personal_auth'],
            'amount_in_cents' => $plan['amountMinor'],
            'currency' => 'COP',
            'signature' => $integritySignature,
            'customer_email' => $transient['customer_email'],
            'reference' => $plan['orderId'],
            'payment_method' => [
                'type' => 'NEQUI',
                'phone_number' => $transient['phone_number'],
            ],
        ];
        $authorization = 'Bearer ' . $transient['private_key'];
        $wireRequest = [
            'httpMethod' => 'POST',
            'targetHost' => 'sandbox.wompi.co',
            'targetPath' => '/v1/transactions',
            'headers' => [
                'Authorization' => $authorization,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
        ];

        $result['valid'] = true;
        $result['status'] = 'transient_wire_request_hashed_and_discarded';
        $result['orderId'] = $plan['orderId'];
        $result['amountMinor'] = $plan['amountMinor'];
        $result['acceptanceTokenSha256'] =
            $consentEvidence['acceptanceTokenSha256'];
        $result['personalAuthTokenSha256'] =
            $consentEvidence['personalAuthTokenSha256'];
        $result['contractsSha256'] = $consentEvidence['contractsSha256'];
        $result['consentEvidenceSha256'] =
            $consentEvidence['consentEvidenceSha256'];
        $result['wireFieldNames'] = array_keys($body);
        $result['paymentMethodFieldNames'] = array_keys(
            $body['payment_method']
        );
        $result['authorizationScheme'] = 'Bearer';
        $result['integrityInputEvidenceSha256'] = hash(
            'sha256',
            "wompi-integrity-input-evidence-v1\0" . $integrityInput
        );
        $result['integritySignatureSha256'] = hash(
            'sha256',
            $integritySignature
        );
        $result['authorizationHeaderSha256'] = hash(
            'sha256',
            $authorization
        );
        $result['wireBodySha256'] = self::hash($body);
        $result['wireRequestSha256'] = self::hash($wireRequest);
        $result['wireRequestConstructed'] = true;
        $result['buildEvidenceSha256'] = self::fingerprint($result);
        if (!self::valid($result)) {
            return self::result('wire_request_encoding_failed');
        }
        return $result;
    }

    public static function valid(array $result): bool
    {
        return array_keys($result) === array_keys(self::result())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null)
                === 'transient_wire_request_hashed_and_discarded'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['method'] ?? null) === 'nequi'
            && ($result['environment'] ?? null) === 'sandbox'
            && ($result['targetHost'] ?? null) === 'sandbox.wompi.co'
            && ($result['targetPath'] ?? null) === '/v1/transactions'
            && ($result['httpMethod'] ?? null) === 'POST'
            && is_string($result['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $result['orderId']
            ) === 1
            && is_int($result['amountMinor'] ?? null)
            && $result['amountMinor'] >= 100
            && $result['amountMinor'] <= 999999999999
            && ($result['currency'] ?? null) === 'COP'
            && self::sha256($result['acceptanceTokenSha256'] ?? null)
            && self::sha256($result['personalAuthTokenSha256'] ?? null)
            && self::sha256($result['contractsSha256'] ?? null)
            && self::sha256($result['consentEvidenceSha256'] ?? null)
            && ($result['wireFieldNames'] ?? null) === [
                'acceptance_token',
                'accept_personal_auth',
                'amount_in_cents',
                'currency',
                'signature',
                'customer_email',
                'reference',
                'payment_method',
            ]
            && ($result['paymentMethodFieldNames'] ?? null) === [
                'type', 'phone_number',
            ]
            && ($result['authorizationScheme'] ?? null) === 'Bearer'
            && self::sha256(
                $result['integrityInputEvidenceSha256'] ?? null
            )
            && self::sha256($result['integritySignatureSha256'] ?? null)
            && self::sha256($result['authorizationHeaderSha256'] ?? null)
            && self::sha256($result['wireBodySha256'] ?? null)
            && self::sha256($result['wireRequestSha256'] ?? null)
            && ($result['wireRequestConstructed'] ?? null) === true
            && ($result['wireRequestReturned'] ?? null) === false
            && ($result['wireRequestPersisted'] ?? null) === false
            && ($result['credentialsReturned'] ?? null) === false
            && ($result['personalDataReturned'] ?? null) === false
            && ($result['rawTokensReturned'] ?? null) === false
            && ($result['integritySignatureReturned'] ?? null) === false
            && ($result['secretResolution'] ?? null) === false
            && ($result['networkAccess'] ?? null) === false
            && ($result['providerContact'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['browserNavigation'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
            && self::sha256($result['buildEvidenceSha256'] ?? null)
            && hash_equals(
                $result['buildEvidenceSha256'],
                self::fingerprint($result)
            )
            && ($result['errors'] ?? null) === [];
    }

    private static function inputsValid(
        array $plan,
        array $orderEvidence,
        array $consentEvidence,
        array $transient,
        int $nowEpoch
    ): bool {
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan)
            || !RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::valid(
                $consentEvidence,
                $nowEpoch
            )
            || !self::orderEvidenceValid($orderEvidence)
            || !self::transientValid($transient)
            || $plan['orderId'] !== $orderEvidence['orderId']
            || $plan['amountMinor'] !== $orderEvidence['amountMinor']
            || $consentEvidence['orderId'] !== $plan['orderId']
            || !hash_equals(
                $orderEvidence['customerEmailSha256'],
                hash('sha256', $transient['customer_email'])
            )
            || !hash_equals(
                $orderEvidence['customerPhoneSha256'],
                hash('sha256', $transient['phone_number'])
            )
            || !hash_equals(
                $consentEvidence['acceptanceTokenSha256'],
                hash('sha256', $transient['acceptance_token'])
            )
            || !hash_equals(
                $consentEvidence['personalAuthTokenSha256'],
                hash('sha256', $transient['accept_personal_auth'])
            )
        ) {
            return false;
        }
        $acceptance = [
            'privacyAccepted' => true,
            'personalDataAccepted' => true,
            'acceptanceTokenSha256' =>
                $consentEvidence['acceptanceTokenSha256'],
            'personalAuthTokenSha256' =>
                $consentEvidence['personalAuthTokenSha256'],
            'contractsSha256' => $consentEvidence['contractsSha256'],
        ];
        $settings = [
            'publicKeySettingPresent' => true,
            'privateKeyReferenceAvailable' => true,
            'integrityKeyReferenceAvailable' => true,
            'eventSecretReferenceAvailable' => true,
        ];
        $replanned = RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::plan(
            $orderEvidence,
            $acceptance,
            $settings
        );
        return RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid(
            $replanned
        ) && hash_equals($plan['planSha256'], $replanned['planSha256']);
    }

    private static function orderEvidenceValid(array $order): bool
    {
        return array_keys($order) === [
            'orderId', 'orderSnapshotSha256', 'amountMinor', 'currency',
            'idempotencySha256', 'customerEmailSha256',
            'customerPhoneSha256',
        ]
            && is_string($order['orderId'] ?? null)
            && preg_match('/\Aord_[a-f0-9]{32}\z/D', $order['orderId']) === 1
            && self::sha256($order['orderSnapshotSha256'] ?? null)
            && is_int($order['amountMinor'] ?? null)
            && $order['amountMinor'] >= 100
            && $order['amountMinor'] <= 999999999999
            && ($order['currency'] ?? null) === 'COP'
            && self::sha256($order['idempotencySha256'] ?? null)
            && self::sha256($order['customerEmailSha256'] ?? null)
            && self::sha256($order['customerPhoneSha256'] ?? null);
    }

    private static function transientValid(array $transient): bool
    {
        return array_keys($transient) === [
            'customer_email',
            'phone_number',
            'acceptance_token',
            'accept_personal_auth',
            'private_key',
            'integrity_secret',
        ]
            && self::email($transient['customer_email'] ?? null)
            && is_string($transient['phone_number'] ?? null)
            && preg_match(
                '/\A3[0-9]{9}\z/D',
                $transient['phone_number']
            ) === 1
            && self::opaqueToken($transient['acceptance_token'] ?? null)
            && self::opaqueToken($transient['accept_personal_auth'] ?? null)
            && $transient['acceptance_token']
                !== $transient['accept_personal_auth']
            && self::sandboxValue(
                $transient['private_key'] ?? null,
                'prv_test_'
            )
            && self::sandboxValue(
                $transient['integrity_secret'] ?? null,
                'test_integrity_'
            );
    }

    private static function email($value): bool
    {
        return is_string($value)
            && strlen($value) >= 3
            && strlen($value) <= 254
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && !preg_match('/[\r\n]/', $value);
    }

    private static function opaqueToken($value): bool
    {
        return is_string($value)
            && strlen($value) >= 20
            && strlen($value) <= 4096
            && preg_match('/\A[A-Za-z0-9._~-]+\z/D', $value) === 1;
    }

    private static function sandboxValue($value, string $prefix): bool
    {
        return is_string($value)
            && strlen($value) >= strlen($prefix) + 16
            && strlen($value) <= 256
            && str_starts_with($value, $prefix)
            && preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) === 1;
    }

    private static function result(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'targetHost' => 'sandbox.wompi.co',
            'targetPath' => '/v1/transactions',
            'httpMethod' => 'POST',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'acceptanceTokenSha256' => '',
            'personalAuthTokenSha256' => '',
            'contractsSha256' => '',
            'consentEvidenceSha256' => '',
            'wireFieldNames' => [],
            'paymentMethodFieldNames' => [],
            'authorizationScheme' => '',
            'integrityInputEvidenceSha256' => '',
            'integritySignatureSha256' => '',
            'authorizationHeaderSha256' => '',
            'wireBodySha256' => '',
            'wireRequestSha256' => '',
            'wireRequestConstructed' => false,
            'wireRequestReturned' => false,
            'wireRequestPersisted' => false,
            'credentialsReturned' => false,
            'personalDataReturned' => false,
            'rawTokensReturned' => false,
            'integritySignatureReturned' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'buildEvidenceSha256' => '',
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
        unset($result['valid'], $result['buildEvidenceSha256']);
        return self::hash($result);
    }
}

?>
