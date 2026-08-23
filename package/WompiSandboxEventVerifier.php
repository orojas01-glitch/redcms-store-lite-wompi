<?php

declare(strict_types=1);

/**
 * Pure C2 verifier for a projected Wompi Sandbox transaction.updated event.
 * Raw HTTP capture and JSON parsing remain outside this package gate.
 */
final class RED_CMS_Store_Lite_Wompi_Sandbox_Event_Verifier
{
    private const MAX_EVENT_AGE = 90000;
    private const ALLOWED_TRANSACTION_FIELDS = [
        'id', 'status', 'amount_in_cents', 'reference', 'currency',
        'payment_method_type',
    ];

    public static function verify(
        array $event,
        array $lookup,
        string $syntheticEventSecret,
        int $now,
        array $seenEvidenceSha256 = []
    ): array {
        $result = self::result('event_refused');
        if (!self::eventShapeValid($event)
            || !self::transactionValid($lookup)
            || strlen($syntheticEventSecret) < 32
            || !array_is_list($seenEvidenceSha256)
        ) {
            $result['errors'] = ['event_refused'];
            return $result;
        }
        foreach ($seenEvidenceSha256 as $seen) {
            if (!self::sha256($seen)) {
                $result['errors'] = ['replay_evidence_invalid'];
                return $result;
            }
        }
        if ($event['timestamp'] > $now
            || $event['timestamp'] < $now - self::MAX_EVENT_AGE
            || $event['sentAtEpoch'] < $event['timestamp']
            || $event['sentAtEpoch'] > $now
        ) {
            $result['errors'] = ['event_time_invalid'];
            return $result;
        }
        $values = self::signedValues($event);
        if (!is_array($values)) {
            $result['errors'] = ['event_properties_invalid'];
            return $result;
        }
        $expectedChecksum = hash(
            'sha256',
            implode('', array_map('strval', $values))
                . (string) $event['timestamp']
                . $syntheticEventSecret
        );
        if (!hash_equals($expectedChecksum, $event['signature']['checksum'])) {
            $result['errors'] = ['event_checksum_invalid'];
            return $result;
        }
        $eventEvidenceSha256 = self::hash([
            'event' => $event['event'],
            'environment' => $event['environment'],
            'properties' => $event['signature']['properties'],
            'checksum' => $event['signature']['checksum'],
            'timestamp' => $event['timestamp'],
        ]);
        if (!self::sha256($eventEvidenceSha256)) {
            $result['errors'] = ['event_evidence_failed'];
            return $result;
        }
        if (in_array($eventEvidenceSha256, $seenEvidenceSha256, true)) {
            $result['errors'] = ['event_replayed'];
            return $result;
        }
        $transaction = $event['data']['transaction'];
        if ($transaction !== $lookup) {
            $result['errors'] = ['event_lookup_mismatch'];
            return $result;
        }
        $outcomes = [
            'APPROVED' => ['paid', true],
            'DECLINED' => ['failed', false],
            'ERROR' => ['failed', false],
        ];
        if (!array_key_exists($transaction['status'], $outcomes)) {
            $result['errors'] = ['event_status_not_final'];
            return $result;
        }
        [$outcome, $paymentVerified] = $outcomes[$transaction['status']];
        $result['valid'] = true;
        $result['status'] = 'event_reconciled';
        $result['providerReference'] = $transaction['id'];
        $result['orderId'] = $transaction['reference'];
        $result['amountMinor'] = $transaction['amount_in_cents'];
        $result['currency'] = 'COP';
        $result['providerStatus'] = $transaction['status'];
        $result['normalizedOutcome'] = $outcome;
        $result['paymentVerified'] = $paymentVerified;
        $result['eventEvidenceSha256'] = $eventEvidenceSha256;
        $result['receivedAtEpoch'] = $event['sentAtEpoch'];
        return $result;
    }

    public static function valid(array $result): bool
    {
        return array_keys($result) === array_keys(self::result())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null) === 'event_reconciled'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['method'] ?? null) === 'nequi'
            && ($result['environment'] ?? null) === 'sandbox'
            && is_string($result['providerReference'] ?? null)
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $result['providerReference']
            ) === 1
            && is_string($result['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $result['orderId']
            ) === 1
            && is_int($result['amountMinor'] ?? null)
            && ($result['currency'] ?? null) === 'COP'
            && in_array(
                $result['providerStatus'] ?? null,
                ['APPROVED', 'DECLINED', 'ERROR'],
                true
            )
            && in_array(
                $result['normalizedOutcome'] ?? null,
                ['paid', 'failed'],
                true
            )
            && is_bool($result['paymentVerified'] ?? null)
            && self::sha256($result['eventEvidenceSha256'] ?? null)
            && is_int($result['receivedAtEpoch'] ?? null)
            && ($result['orderMutationAuthorized'] ?? null) === false
            && ($result['providerContact'] ?? null) === false
            && ($result['paymentApplied'] ?? null) === false
            && ($result['errors'] ?? null) === [];
    }

    private static function result(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'providerReference' => '',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'providerStatus' => '',
            'normalizedOutcome' => '',
            'paymentVerified' => false,
            'eventEvidenceSha256' => '',
            'receivedAtEpoch' => 0,
            'orderMutationAuthorized' => false,
            'providerContact' => false,
            'paymentApplied' => false,
            'errors' => [],
        ];
    }

    private static function eventShapeValid(array $event): bool
    {
        return array_keys($event) === [
            'event', 'data', 'environment', 'signature', 'timestamp',
            'sentAtEpoch',
        ]
            && ($event['event'] ?? null) === 'transaction.updated'
            && ($event['environment'] ?? null) === 'test'
            && is_array($event['data'] ?? null)
            && array_keys($event['data']) === ['transaction']
            && is_array($event['data']['transaction'] ?? null)
            && self::transactionValid($event['data']['transaction'])
            && is_array($event['signature'] ?? null)
            && array_keys($event['signature']) === ['properties', 'checksum']
            && is_array($event['signature']['properties'] ?? null)
            && array_is_list($event['signature']['properties'])
            && count($event['signature']['properties']) >= 1
            && count($event['signature']['properties']) <= 16
            && self::sha256($event['signature']['checksum'] ?? null)
            && is_int($event['timestamp'] ?? null)
            && is_int($event['sentAtEpoch'] ?? null);
    }

    private static function transactionValid(array $transaction): bool
    {
        return array_keys($transaction) === self::ALLOWED_TRANSACTION_FIELDS
            && is_string($transaction['id'] ?? null)
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $transaction['id']
            ) === 1
            && in_array(
                $transaction['status'] ?? null,
                ['PENDING', 'APPROVED', 'DECLINED', 'ERROR'],
                true
            )
            && is_int($transaction['amount_in_cents'] ?? null)
            && $transaction['amount_in_cents'] >= 100
            && $transaction['amount_in_cents'] <= 999999999999
            && is_string($transaction['reference'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $transaction['reference']
            ) === 1
            && ($transaction['currency'] ?? null) === 'COP'
            && ($transaction['payment_method_type'] ?? null) === 'NEQUI';
    }

    private static function signedValues(array $event): ?array
    {
        $transaction = $event['data']['transaction'];
        $values = [];
        $seen = [];
        foreach ($event['signature']['properties'] as $property) {
            if (!is_string($property)
                || preg_match(
                    '/\Atransaction\.([a-z][a-z0-9_]{0,63})\z/D',
                    $property,
                    $match
                ) !== 1
                || !in_array(
                    $match[1],
                    self::ALLOWED_TRANSACTION_FIELDS,
                    true
                )
                || isset($seen[$property])
                || !array_key_exists($match[1], $transaction)
            ) {
                return null;
            }
            $value = $transaction[$match[1]];
            if (!is_string($value) && !is_int($value)) {
                return null;
            }
            $seen[$property] = true;
            $values[] = $value;
        }
        return $values;
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
}

?>
