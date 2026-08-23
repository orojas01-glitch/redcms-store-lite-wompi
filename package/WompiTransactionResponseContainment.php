<?php

declare(strict_types=1);

/** Pure C4B3 containment for synthetic Wompi create and lookup responses. */
final class RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment
{
    private const MAX_RESPONSE_BYTES = 65536;

    public static function create(
        array $plan,
        array $wireEvidence,
        int $httpStatus,
        array $response
    ): array {
        $result = self::createResult('create_response_refused');
        $transaction = self::transaction($response, ['PENDING']);
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan)
            || !RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::valid(
                $wireEvidence
            )
            || $httpStatus !== 201
            || !is_array($transaction)
            || !self::wireMatches($plan, $wireEvidence, $transaction)
        ) {
            $result['errors'] = ['create_response_refused'];
            return $result;
        }

        $safe = self::safeProjection($transaction);
        $accepted = RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::accept(
            $plan,
            $safe
        );
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::valid($accepted)) {
            $result['errors'] = ['create_response_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'create_response_contained';
        $result['providerReference'] = $safe['id'];
        $result['reference'] = $safe['reference'];
        $result['amountMinor'] = $safe['amount_in_cents'];
        $result['transactionStatus'] = 'PENDING';
        $result['initiation'] = $accepted['initiation'];
        $result['wireRequestSha256'] = $wireEvidence['wireRequestSha256'];
        $result['consentEvidenceSha256'] =
            $wireEvidence['consentEvidenceSha256'];
        $result['discardedFieldNames'] = self::discardedFieldNames(
            $response['data']
        );
        $result['responseProjectionSha256'] = self::hash($safe);
        $result['createEvidenceSha256'] = self::fingerprint($result);
        if (!self::validCreate($result)) {
            return self::createResult('create_response_encoding_failed');
        }
        return $result;
    }

    public static function lookup(
        array $createEvidence,
        int $httpStatus,
        array $response
    ): array {
        $result = self::lookupResult('lookup_response_refused');
        $transaction = self::transaction(
            $response,
            ['PENDING', 'APPROVED', 'DECLINED', 'ERROR']
        );
        if (!self::validCreate($createEvidence)
            || $httpStatus !== 200
            || !is_array($transaction)
            || !self::createMatches($createEvidence, $transaction)
        ) {
            $result['errors'] = ['lookup_response_refused'];
            return $result;
        }

        $safe = self::safeProjection($transaction);
        $status = $safe['status'];
        $result['valid'] = true;
        $result['status'] = 'lookup_response_contained';
        $result['providerReference'] = $safe['id'];
        $result['reference'] = $safe['reference'];
        $result['amountMinor'] = $safe['amount_in_cents'];
        $result['transactionStatus'] = $status;
        $result['final'] = $status !== 'PENDING';
        $result['proposedOutcome'] = $status === 'APPROVED'
            ? 'paid'
            : (in_array($status, ['DECLINED', 'ERROR'], true)
                ? 'failed'
                : 'pending');
        $result['createEvidenceSha256'] =
            $createEvidence['createEvidenceSha256'];
        $result['wireRequestSha256'] =
            $createEvidence['wireRequestSha256'];
        $result['discardedFieldNames'] = self::discardedFieldNames(
            $response['data']
        );
        $result['responseProjectionSha256'] = self::hash($safe);
        $result['lookupEvidenceSha256'] = self::fingerprint($result);
        if (!self::validLookup($result)) {
            return self::lookupResult('lookup_response_encoding_failed');
        }
        return $result;
    }

    public static function validCreate(array $result): bool
    {
        return array_keys($result) === array_keys(self::createResult())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null) === 'create_response_contained'
            && self::commonResultValid($result)
            && ($result['transactionStatus'] ?? null) === 'PENDING'
            && ($result['initiation'] ?? null) === [
                'accepted' => true,
                'mode' => 'out_of_band_confirmation',
                'reason' => 'initiation_accepted',
                'value' => [
                    'providerReference' => $result['providerReference'],
                    'state' => 'pending',
                    'customerAction' => 'approve_in_provider_app',
                ],
            ]
            && self::sha256($result['consentEvidenceSha256'] ?? null)
            && self::sha256($result['createEvidenceSha256'] ?? null)
            && hash_equals(
                $result['createEvidenceSha256'],
                self::fingerprint($result)
            )
            && ($result['paymentVerified'] ?? null) === false
            && ($result['eventAgreement'] ?? null) === false;
    }

    public static function validLookup(array $result): bool
    {
        $status = $result['transactionStatus'] ?? null;
        $expectedFinal = in_array(
            $status,
            ['APPROVED', 'DECLINED', 'ERROR'],
            true
        );
        $expectedOutcome = $status === 'APPROVED'
            ? 'paid'
            : (in_array($status, ['DECLINED', 'ERROR'], true)
                ? 'failed'
                : 'pending');
        return array_keys($result) === array_keys(self::lookupResult())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null) === 'lookup_response_contained'
            && self::commonResultValid($result)
            && in_array(
                $status,
                ['PENDING', 'APPROVED', 'DECLINED', 'ERROR'],
                true
            )
            && ($result['final'] ?? null) === $expectedFinal
            && ($result['proposedOutcome'] ?? null) === $expectedOutcome
            && self::sha256($result['createEvidenceSha256'] ?? null)
            && self::sha256($result['lookupEvidenceSha256'] ?? null)
            && hash_equals(
                $result['lookupEvidenceSha256'],
                self::fingerprint($result)
            )
            && ($result['paymentVerified'] ?? null) === false
            && ($result['eventAgreement'] ?? null) === false;
    }

    private static function commonResultValid(array $result): bool
    {
        return ($result['provider'] ?? null) === 'wompi'
            && ($result['method'] ?? null) === 'nequi'
            && ($result['environment'] ?? null) === 'sandbox'
            && is_string($result['providerReference'] ?? null)
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $result['providerReference']
            ) === 1
            && is_string($result['reference'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $result['reference']
            ) === 1
            && is_int($result['amountMinor'] ?? null)
            && $result['amountMinor'] >= 100
            && $result['amountMinor'] <= 999999999999
            && ($result['currency'] ?? null) === 'COP'
            && ($result['paymentMethodType'] ?? null) === 'NEQUI'
            && self::sha256($result['wireRequestSha256'] ?? null)
            && self::discardedNamesValid(
                $result['discardedFieldNames'] ?? null
            )
            && self::sha256($result['responseProjectionSha256'] ?? null)
            && ($result['rawResponseReturned'] ?? null) === false
            && ($result['rawHeadersReturned'] ?? null) === false
            && ($result['discardedFieldsReturned'] ?? null) === false
            && ($result['personalDataReturned'] ?? null) === false
            && ($result['networkAccess'] ?? null) === false
            && ($result['providerContact'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['paymentApplied'] ?? null) === false
            && ($result['orderMutationAuthorized'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
            && ($result['errors'] ?? null) === [];
    }

    private static function transaction(array $response, array $statuses)
    {
        if (array_keys($response) !== ['data']
            || !is_array($response['data'] ?? null)
            || !self::responseSizeValid($response)
        ) {
            return null;
        }
        $data = $response['data'];
        $keys = array_keys($data);
        sort($keys, SORT_STRING);
        $required = [
            'amount_in_cents', 'currency', 'id', 'payment_method_type',
            'reference', 'status',
        ];
        $allowed = array_merge($required, [
            'created_at', 'customer_email', 'merchant', 'payment_method',
            'status_message',
        ]);
        sort($allowed, SORT_STRING);
        if (array_diff($required, $keys) !== []
            || array_diff($keys, $allowed) !== []
            || !is_string($data['id'] ?? null)
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $data['id']
            ) !== 1
            || !is_string($data['reference'] ?? null)
            || preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $data['reference']
            ) !== 1
            || !is_int($data['amount_in_cents'] ?? null)
            || $data['amount_in_cents'] < 100
            || $data['amount_in_cents'] > 999999999999
            || ($data['currency'] ?? null) !== 'COP'
            || ($data['payment_method_type'] ?? null) !== 'NEQUI'
            || !in_array($data['status'] ?? null, $statuses, true)
            || !self::optionalFieldsValid($data)
        ) {
            return null;
        }
        return $data;
    }

    private static function optionalFieldsValid(array $data): bool
    {
        foreach (['created_at', 'status_message'] as $key) {
            if (array_key_exists($key, $data)
                && !self::boundedText($data[$key], 512)
            ) {
                return false;
            }
        }
        if (array_key_exists('customer_email', $data)
            && (!is_string($data['customer_email'])
                || strlen($data['customer_email']) > 254
                || filter_var(
                    $data['customer_email'],
                    FILTER_VALIDATE_EMAIL
                ) === false
                || preg_match('/[\r\n]/', $data['customer_email']))
        ) {
            return false;
        }
        foreach (['merchant', 'payment_method'] as $key) {
            if (array_key_exists($key, $data)
                && !self::flatDiscardedObject($data[$key])
            ) {
                return false;
            }
        }
        return true;
    }

    private static function flatDiscardedObject($value): bool
    {
        if (!is_array($value)
            || array_is_list($value)
            || count($value) > 16
        ) {
            return false;
        }
        foreach ($value as $key => $item) {
            if (!is_string($key)
                || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key) !== 1
                || is_array($item)
                || is_object($item)
                || is_resource($item)
                || (is_string($item) && !self::boundedText($item, 512))
            ) {
                return false;
            }
        }
        return true;
    }

    private static function boundedText($value, int $max): bool
    {
        return is_string($value)
            && strlen($value) <= $max
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
                !== 1;
    }

    private static function responseSizeValid(array $response): bool
    {
        try {
            $encoded = json_encode(
                $response,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return false;
        }
        return strlen($encoded) <= self::MAX_RESPONSE_BYTES;
    }

    private static function wireMatches(
        array $plan,
        array $wire,
        array $transaction
    ): bool {
        return $plan['orderId'] === $wire['orderId']
            && $plan['orderId'] === $transaction['reference']
            && $plan['amountMinor'] === $wire['amountMinor']
            && $plan['amountMinor'] === $transaction['amount_in_cents']
            && $wire['currency'] === 'COP'
            && $transaction['currency'] === 'COP'
            && $wire['method'] === 'nequi'
            && $transaction['payment_method_type'] === 'NEQUI';
    }

    private static function createMatches(
        array $create,
        array $transaction
    ): bool {
        return $create['providerReference'] === $transaction['id']
            && $create['reference'] === $transaction['reference']
            && $create['amountMinor'] === $transaction['amount_in_cents']
            && $create['currency'] === $transaction['currency']
            && $create['paymentMethodType']
                === $transaction['payment_method_type'];
    }

    private static function safeProjection(array $transaction): array
    {
        return [
            'id' => $transaction['id'],
            'status' => $transaction['status'],
            'amount_in_cents' => $transaction['amount_in_cents'],
            'reference' => $transaction['reference'],
            'currency' => $transaction['currency'],
            'payment_method_type' => $transaction['payment_method_type'],
        ];
    }

    private static function discardedFieldNames(array $transaction): array
    {
        $names = array_values(array_intersect(
            array_keys($transaction),
            [
                'created_at', 'customer_email', 'merchant',
                'payment_method', 'status_message',
            ]
        ));
        sort($names, SORT_STRING);
        return $names;
    }

    private static function discardedNamesValid($names): bool
    {
        if (!is_array($names) || !array_is_list($names)) {
            return false;
        }
        $allowed = [
            'created_at', 'customer_email', 'merchant',
            'payment_method', 'status_message',
        ];
        $sorted = $names;
        sort($sorted, SORT_STRING);
        return $names === $sorted
            && count(array_unique($names, SORT_STRING)) === count($names)
            && array_diff($names, $allowed) === [];
    }

    private static function createResult(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'providerReference' => '',
            'reference' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'paymentMethodType' => 'NEQUI',
            'transactionStatus' => '',
            'initiation' => null,
            'wireRequestSha256' => '',
            'consentEvidenceSha256' => '',
            'discardedFieldNames' => [],
            'responseProjectionSha256' => '',
            'createEvidenceSha256' => '',
            'paymentVerified' => false,
            'eventAgreement' => false,
            'rawResponseReturned' => false,
            'rawHeadersReturned' => false,
            'discardedFieldsReturned' => false,
            'personalDataReturned' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'paymentApplied' => false,
            'orderMutationAuthorized' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }

    private static function lookupResult(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'providerReference' => '',
            'reference' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'paymentMethodType' => 'NEQUI',
            'transactionStatus' => '',
            'final' => false,
            'proposedOutcome' => '',
            'createEvidenceSha256' => '',
            'wireRequestSha256' => '',
            'discardedFieldNames' => [],
            'responseProjectionSha256' => '',
            'lookupEvidenceSha256' => '',
            'paymentVerified' => false,
            'eventAgreement' => false,
            'rawResponseReturned' => false,
            'rawHeadersReturned' => false,
            'discardedFieldsReturned' => false,
            'personalDataReturned' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'paymentApplied' => false,
            'orderMutationAuthorized' => false,
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
        unset(
            $result['valid'],
            $result['createEvidenceSha256'],
            $result['lookupEvidenceSha256']
        );
        return self::hash($result);
    }
}

?>
