<?php

declare(strict_types=1);

/** Pure C2 projection of one synthetic Wompi PENDING transaction. */
final class RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate
{
    public static function accept(array $plan, array $response): array
    {
        $result = self::result('response_refused');
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan)
            || array_keys($response) !== [
                'id', 'status', 'amount_in_cents', 'reference', 'currency',
                'payment_method_type',
            ]
            || !is_string($response['id'] ?? null)
            || preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,159}\z/D',
                $response['id']
            ) !== 1
            || ($response['status'] ?? null) !== 'PENDING'
            || ($response['amount_in_cents'] ?? null)
                !== $plan['amountMinor']
            || ($response['reference'] ?? null) !== $plan['orderId']
            || ($response['currency'] ?? null) !== 'COP'
            || ($response['payment_method_type'] ?? null) !== 'NEQUI'
        ) {
            $result['errors'] = ['response_refused'];
            return $result;
        }
        $responseEvidenceSha256 = self::hash($response);
        if (!self::sha256($responseEvidenceSha256)) {
            $result['status'] = 'response_encoding_failed';
            $result['errors'] = ['response_encoding_failed'];
            return $result;
        }
        $result['valid'] = true;
        $result['status'] = 'pending_contract_accepted';
        $result['initiation'] = [
            'accepted' => true,
            'mode' => 'out_of_band_confirmation',
            'reason' => 'initiation_accepted',
            'value' => [
                'providerReference' => $response['id'],
                'state' => 'pending',
                'customerAction' => 'approve_in_provider_app',
            ],
        ];
        $result['responseEvidenceSha256'] = $responseEvidenceSha256;
        return $result;
    }

    public static function valid(array $result): bool
    {
        return array_keys($result) === array_keys(self::result())
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null) === 'pending_contract_accepted'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['method'] ?? null) === 'nequi'
            && ($result['environment'] ?? null) === 'sandbox'
            && is_array($result['initiation'] ?? null)
            && array_keys($result['initiation']) === [
                'accepted', 'mode', 'reason', 'value',
            ]
            && ($result['initiation']['accepted'] ?? null) === true
            && ($result['initiation']['mode'] ?? null)
                === 'out_of_band_confirmation'
            && ($result['initiation']['reason'] ?? null)
                === 'initiation_accepted'
            && is_array($result['initiation']['value'] ?? null)
            && array_keys($result['initiation']['value']) === [
                'providerReference', 'state', 'customerAction',
            ]
            && is_string(
                $result['initiation']['value']['providerReference'] ?? null
            )
            && ($result['initiation']['value']['state'] ?? null) === 'pending'
            && ($result['initiation']['value']['customerAction'] ?? null)
                === 'approve_in_provider_app'
            && self::sha256($result['responseEvidenceSha256'] ?? null)
            && ($result['providerContact'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['webhook'] ?? null) === false
            && ($result['browserNavigation'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
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
            'initiation' => null,
            'responseEvidenceSha256' => '',
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
