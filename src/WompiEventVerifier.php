<?php

declare(strict_types=1);

require_once __DIR__ . '/WompiEnvironment.php';

/**
 * Pure W1 verifier for Wompi transaction.updated events (Sandbox and
 * production) received by the Web Checkout integration.
 *
 * Step 1, verify(): parses one raw request body, checks the event checksum
 * (SHA-256 of the values named in signature.properties, then timestamp, then
 * the events secret), the configured environment, event age and replay, and
 * returns a bounded projection with no customer data.
 *
 * Step 2, reconcile(): requires a server-side transaction lookup to agree
 * with the signed event and the stored order (reference, amount, currency),
 * because Wompi signs only the properties it lists (by default id, status and
 * amount). Only then does it produce the provider-neutral Store Lite
 * payment-event input. This class performs no HTTP, database or logging.
 */
final class RED_CMS_Store_Lite_Wompi_Event_Verifier
{
    public const PAYMENT_METHOD = 'wompi_checkout';
    private const MAX_BODY_BYTES = 65536;
    private const MAX_EVENT_AGE = 90000;
    private const MAX_CLOCK_SKEW = 300;
    private const PROJECTED_FIELDS = [
        'id', 'status', 'amount_in_cents', 'reference', 'currency',
        'payment_method_type',
    ];
    private const SIGNABLE_FIELDS = [
        'id', 'status', 'amount_in_cents', 'reference', 'currency',
        'payment_method_type', 'customer_email', 'redirect_url',
        'created_at', 'finalized_at', 'status_message',
    ];
    private const OUTCOMES = [
        'APPROVED' => 'paid',
        'DECLINED' => 'failed',
        'ERROR' => 'failed',
        'VOIDED' => 'cancelled',
    ];

    public static function verify(
        string $rawBody,
        string $headerChecksum,
        string $environment,
        string $eventsSecret,
        int $now,
        array $seenEvidenceSha256 = []
    ): array {
        $result = self::result();
        $profile = RED_CMS_Store_Lite_Wompi_Environment::resolve(
            $environment,
            ['events' => $eventsSecret]
        );
        if ($profile['valid'] !== true) {
            return self::fail($result, 'event_secret_environment_invalid');
        }
        if ($rawBody === '' || strlen($rawBody) > self::MAX_BODY_BYTES) {
            return self::fail($result, 'event_body_size_invalid');
        }
        try {
            $event = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return self::fail($result, 'event_json_invalid');
        }
        if (!is_array($event)
            || ($event['event'] ?? null) !== 'transaction.updated'
            || !is_array($event['data'] ?? null)
            || !is_array($event['data']['transaction'] ?? null)
            || !is_array($event['signature'] ?? null)
            || !is_array($event['signature']['properties'] ?? null)
            || !is_string($event['signature']['checksum'] ?? null)
            || !is_int($event['timestamp'] ?? null)
        ) {
            return self::fail($result, 'event_shape_invalid');
        }
        if (($event['environment'] ?? null) !== $profile['eventEnvironment']) {
            return self::fail($result, 'event_environment_mismatch');
        }

        $bodyChecksum = strtolower($event['signature']['checksum']);
        if (!self::sha256($bodyChecksum)) {
            return self::fail($result, 'event_checksum_invalid');
        }
        if ($headerChecksum !== ''
            && !hash_equals($bodyChecksum, strtolower($headerChecksum))
        ) {
            return self::fail($result, 'event_header_checksum_mismatch');
        }

        $transaction = $event['data']['transaction'];
        $values = self::signedValues(
            $event['signature']['properties'],
            $transaction
        );
        if ($values === null) {
            return self::fail($result, 'event_properties_invalid');
        }
        $expected = hash(
            'sha256',
            implode('', $values) . $event['timestamp'] . $eventsSecret
        );
        if (!hash_equals($expected, $bodyChecksum)) {
            return self::fail($result, 'event_checksum_invalid');
        }

        if ($event['timestamp'] > $now + self::MAX_CLOCK_SKEW
            || $event['timestamp'] < $now - self::MAX_EVENT_AGE
        ) {
            return self::fail($result, 'event_time_invalid');
        }

        $projection = self::projectTransaction($transaction);
        if ($projection === null) {
            return self::fail($result, 'event_transaction_invalid');
        }

        $evidence = hash('sha256', 'redcms.wompi.event.v1|' . json_encode([
            $event['event'],
            $event['environment'],
            $projection['id'],
            $projection['status'],
            $bodyChecksum,
            $event['timestamp'],
        ], JSON_UNESCAPED_SLASHES));
        foreach ($seenEvidenceSha256 as $seen) {
            if (!is_string($seen) || !self::sha256($seen)) {
                return self::fail($result, 'replay_evidence_invalid');
            }
            if (hash_equals($seen, $evidence)) {
                $result['replayed'] = true;
                $result['eventEvidenceSha256'] = $evidence;
                return self::fail($result, 'event_replayed');
            }
        }

        $result['valid'] = true;
        $result['status'] = 'event_verified';
        $result['environment'] = $profile['environment'];
        $result['transaction'] = $projection;
        $result['eventEvidenceSha256'] = $evidence;
        $result['occurredAt'] = $event['timestamp'];
        return $result;
    }

    /**
     * Parses a GET /v1/transactions/{id} response body into the same bounded
     * projection. Customer data in the response is discarded.
     */
    public static function projectLookup(string $rawBody): ?array
    {
        if ($rawBody === '' || strlen($rawBody) > self::MAX_BODY_BYTES) {
            return null;
        }
        try {
            $decoded = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return null;
        }
        if (!is_array($decoded) || !is_array($decoded['data'] ?? null)) {
            return null;
        }
        return self::projectTransaction($decoded['data']);
    }

    /**
     * @param array $verified result of verify()
     * @param array $lookup projection from projectLookup()
     * @param array{orderId: string, snapshotSha256: string,
     *     amountMinor: int, currency: string} $order stored order
     */
    public static function reconcile(
        array $verified,
        ?array $lookup,
        array $order
    ): array {
        $result = [
            'valid' => false,
            'final' => false,
            'storeLiteEvent' => null,
            'errors' => [],
        ];
        if (($verified['valid'] ?? null) !== true
            || !is_array($verified['transaction'] ?? null)
        ) {
            $result['errors'] = ['event_not_verified'];
            return $result;
        }
        if ($lookup === null) {
            $result['errors'] = ['lookup_unavailable'];
            return $result;
        }
        $transaction = $verified['transaction'];
        if ($transaction !== $lookup) {
            $result['errors'] = ['event_lookup_mismatch'];
            return $result;
        }
        if (array_keys($order) !== [
            'orderId', 'snapshotSha256', 'amountMinor', 'currency',
        ]
            || !is_string($order['orderId'] ?? null)
            || !self::sha256($order['snapshotSha256'] ?? null)
            || !is_int($order['amountMinor'] ?? null)
            || !is_string($order['currency'] ?? null)
        ) {
            $result['errors'] = ['order_invalid'];
            return $result;
        }
        if (!hash_equals($order['orderId'], $transaction['reference'])) {
            $result['errors'] = ['order_reference_mismatch'];
            return $result;
        }
        if ($order['amountMinor'] !== $transaction['amount_in_cents']) {
            $result['errors'] = ['order_amount_mismatch'];
            return $result;
        }
        if ($order['currency'] !== $transaction['currency']) {
            $result['errors'] = ['order_currency_mismatch'];
            return $result;
        }
        $outcome = self::OUTCOMES[$transaction['status']] ?? null;
        $result['valid'] = true;
        if ($outcome === null) {
            // PENDING: verified but not final; acknowledge, change nothing.
            return $result;
        }
        $result['final'] = true;
        $result['storeLiteEvent'] = [
            'verification' => 'verified',
            'replayStatus' => 'unseen',
            'outcome' => $outcome,
            'orderId' => $order['orderId'],
            'orderSnapshotSha256' => $order['snapshotSha256'],
            'paymentMethod' => self::PAYMENT_METHOD,
            'amountMinor' => $transaction['amount_in_cents'],
            'currency' => $transaction['currency'],
            'eventEvidenceSha256' => $verified['eventEvidenceSha256'],
            'occurredAt' => $verified['occurredAt'],
        ];
        return $result;
    }

    private static function signedValues(
        array $properties,
        array $transaction
    ): ?array {
        if ($properties === []
            || !array_is_list($properties)
            || count($properties) > 16
        ) {
            return null;
        }
        $values = [];
        $seen = [];
        foreach ($properties as $property) {
            if (!is_string($property)
                || preg_match(
                    '/\Atransaction\.([a-z][a-z0-9_]{0,63})\z/D',
                    $property,
                    $match
                ) !== 1
                || !in_array($match[1], self::SIGNABLE_FIELDS, true)
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
            $values[] = (string) $value;
        }
        return $values;
    }

    private static function projectTransaction(array $transaction): ?array
    {
        $projection = [];
        foreach (self::PROJECTED_FIELDS as $field) {
            if (!array_key_exists($field, $transaction)) {
                return null;
            }
            $projection[$field] = $transaction[$field];
        }
        $valid = is_string($projection['id'])
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $projection['id']
            ) === 1
            && in_array(
                $projection['status'],
                ['PENDING', 'APPROVED', 'DECLINED', 'VOIDED', 'ERROR'],
                true
            )
            && is_int($projection['amount_in_cents'])
            && $projection['amount_in_cents'] >= 100
            && $projection['amount_in_cents'] <= 999999999999
            && is_string($projection['reference'])
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $projection['reference']
            ) === 1
            && $projection['currency'] === 'COP'
            && is_string($projection['payment_method_type'])
            && preg_match(
                '/\A[A-Z][A-Z0-9_]{1,39}\z/D',
                $projection['payment_method_type']
            ) === 1;
        return $valid ? $projection : null;
    }

    private static function result(): array
    {
        return [
            'valid' => false,
            'status' => 'event_refused',
            'environment' => '',
            'transaction' => null,
            'eventEvidenceSha256' => '',
            'occurredAt' => 0,
            'replayed' => false,
            'orderMutationAuthorized' => false,
            'errors' => [],
        ];
    }

    private static function fail(array $result, string $error): array
    {
        $result['errors'] = [$error];
        return $result;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}

?>
