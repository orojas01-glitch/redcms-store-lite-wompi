<?php

declare(strict_types=1);

require_once __DIR__ . '/WompiEnvironment.php';
require_once __DIR__ . '/WompiEventVerifier.php';

/**
 * W3 provider-events coordinator for Wompi Web Checkout.
 *
 * Runs one raw transaction.updated delivery through the full decision:
 * verify the signed event, confirm it with a server-side GET of the
 * transaction, reconcile it with the stored Store Lite order, and hand the
 * provider-neutral event to Store Lite's payment.event.apply. It returns the
 * HTTP status Wompi should receive: 200 when the delivery is final (applied,
 * replayed, pending, or permanently refused for this order) and 401 or 503
 * only when a retry by Wompi is either pointless for a forgery or needed for
 * a transient failure.
 *
 * Every side effect is an injected callable, so this class performs no HTTP,
 * database, secret resolution or logging itself:
 *
 * - $lookup(string $url): ?string   raw body of GET {api}/transactions/{id}
 * - $loadOrder(string $orderId): ?array
 *       the Store Lite expected-order projection (orderId, snapshotSha256,
 *       paymentMethod, paymentKind, currency, totalMinor, orderStatus,
 *       paymentStatus, fulfillmentStatus), or null when unknown
 * - $apply(array $order, array $event): array
 *       ['ok' => bool, 'status' => string] for success or
 *       ['ok' => false, 'error' => string] with a Store Lite error code
 */
final class RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator
{
    private const ORDER_KEYS = [
        'orderId', 'snapshotSha256', 'paymentMethod', 'paymentKind',
        'currency', 'totalMinor', 'orderStatus', 'paymentStatus',
        'fulfillmentStatus',
    ];
    private const RETRYABLE_APPLY_ERRORS = [
        'payment_event_storage_unavailable',
        'payment_order_state_conflict',
    ];

    public static function handle(
        string $rawBody,
        string $headerChecksum,
        string $environment,
        string $eventsSecret,
        int $now,
        array $seenEvidenceSha256,
        callable $lookup,
        callable $loadOrder,
        callable $apply
    ): array {
        $verified = RED_CMS_Store_Lite_Wompi_Event_Verifier::verify(
            $rawBody,
            $headerChecksum,
            $environment,
            $eventsSecret,
            $now,
            $seenEvidenceSha256
        );
        if ($verified['valid'] !== true) {
            $error = $verified['errors'][0] ?? 'event_refused';
            if ($verified['replayed'] === true) {
                return self::result(200, 'replayed', $error, $verified);
            }
            if ($error === 'event_secret_environment_invalid'
                || $error === 'replay_evidence_invalid'
            ) {
                return self::result(503, 'configuration_invalid', $error);
            }
            return self::result(401, 'event_refused', $error);
        }

        $profile = RED_CMS_Store_Lite_Wompi_Environment::resolve(
            $environment,
            ['events' => $eventsSecret]
        );
        $url = $profile['apiBase'] . '/transactions/'
            . rawurlencode($verified['transaction']['id']);
        try {
            $lookupBody = $lookup($url);
        } catch (Throwable $throwable) {
            $lookupBody = null;
        }
        $lookupProjection = is_string($lookupBody)
            ? RED_CMS_Store_Lite_Wompi_Event_Verifier::projectLookup(
                $lookupBody
            )
            : null;
        if ($lookupProjection === null) {
            return self::result(
                503,
                'lookup_unavailable',
                'lookup_unavailable',
                $verified
            );
        }
        if ($lookupProjection !== $verified['transaction']) {
            // The transaction moved on or the event is stale; let Wompi
            // retry so the final event and lookup can agree.
            return self::result(
                503,
                'lookup_disagrees',
                'event_lookup_mismatch',
                $verified
            );
        }

        try {
            $order = $loadOrder($verified['transaction']['reference']);
        } catch (Throwable $throwable) {
            return self::result(
                503,
                'order_unavailable',
                'order_unavailable',
                $verified
            );
        }
        if ($order === null) {
            return self::result(200, 'order_unknown', 'order_unknown', $verified);
        }
        if (!is_array($order)
            || array_keys($order) !== self::ORDER_KEYS
            || !is_int($order['totalMinor'] ?? null)
            || !is_string($order['orderId'] ?? null)
            || !is_string($order['snapshotSha256'] ?? null)
            || !is_string($order['currency'] ?? null)
        ) {
            return self::result(
                503,
                'order_unavailable',
                'order_projection_invalid',
                $verified
            );
        }
        if ($order['paymentMethod'] !== RED_CMS_Store_Lite_Wompi_Event_Verifier::PAYMENT_METHOD
            || $order['paymentKind'] !== 'hosted'
        ) {
            return self::result(
                200,
                'order_mismatch',
                'order_payment_method_mismatch',
                $verified
            );
        }

        $reconciled = RED_CMS_Store_Lite_Wompi_Event_Verifier::reconcile(
            $verified,
            $lookupProjection,
            [
                'orderId' => $order['orderId'],
                'snapshotSha256' => $order['snapshotSha256'],
                'amountMinor' => $order['totalMinor'],
                'currency' => $order['currency'],
            ]
        );
        if ($reconciled['valid'] !== true) {
            return self::result(
                200,
                'order_mismatch',
                $reconciled['errors'][0] ?? 'order_mismatch',
                $verified
            );
        }
        if ($reconciled['final'] !== true) {
            return self::result(200, 'pending', '', $verified);
        }

        try {
            $applied = $apply($order, $reconciled['storeLiteEvent']);
        } catch (Throwable $throwable) {
            $applied = [
                'ok' => false,
                'error' => 'payment_event_storage_unavailable',
            ];
        }
        if (is_array($applied) && ($applied['ok'] ?? null) === true) {
            $status = $applied['status'] ?? '';
            if (!in_array($status, ['applied', 'replayed', 'unchanged'], true)) {
                return self::result(
                    503,
                    'apply_unavailable',
                    'apply_result_invalid',
                    $verified
                );
            }
            $result = self::result(200, $status, '', $verified);
            $result['outcome'] = $reconciled['storeLiteEvent']['outcome'];
            return $result;
        }
        $error = is_array($applied) && is_string($applied['error'] ?? null)
            ? $applied['error']
            : 'payment_event_storage_unavailable';
        if (in_array($error, self::RETRYABLE_APPLY_ERRORS, true)) {
            return self::result(503, 'apply_unavailable', $error, $verified);
        }
        return self::result(200, 'apply_refused', $error, $verified);
    }

    private static function result(
        int $httpStatus,
        string $disposition,
        string $error,
        ?array $verified = null
    ): array {
        return [
            'httpStatus' => $httpStatus,
            'disposition' => $disposition,
            'outcome' => '',
            'eventEvidenceSha256' => is_array($verified)
                ? (string) ($verified['eventEvidenceSha256'] ?? '')
                : '',
            'transactionId' => is_array($verified['transaction'] ?? null)
                ? $verified['transaction']['id']
                : '',
            'orderId' => is_array($verified['transaction'] ?? null)
                ? $verified['transaction']['reference']
                : '',
            'error' => $error,
        ];
    }
}

?>
