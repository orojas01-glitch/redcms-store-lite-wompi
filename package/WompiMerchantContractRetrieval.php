<?php

declare(strict_types=1);

/** One-attempt C4C merchant-contract retrieval and response containment. */
final class RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval
{
    public static function execute(
        array $plan,
        string $publicKey,
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport $transport
    ): array {
        $result = self::result('retrieval_refused');
        if (!RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::valid(
            $plan
        )
            || !self::publicKeyValid($publicKey)
            || !hash_equals($plan['publicKeySha256'], hash('sha256', $publicKey))
        ) {
            $result['errors'] = ['retrieval_refused'];
            return $result;
        }
        $result['planSha256'] = $plan['planSha256'];
        $result['publicKeySha256'] = $plan['publicKeySha256'];
        $url = 'https://sandbox.wompi.co/v1/merchants/' . $publicKey;
        $result['requestSha256'] = self::hash([
            'method' => 'GET',
            'urlSha256' => hash('sha256', $url),
            'planSha256' => $plan['planSha256'],
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
        ]);
        $transportResult = null;
        try {
            $transportResult = $transport->get($url, 65536);
            $result['executionPerformed'] = true;
        } catch (Throwable $throwable) {
            $result['executionPerformed'] = true;
            $result['status'] = 'transport_indeterminate';
            $result['errors'] = ['transport_indeterminate'];
            $publicKey = '';
            $url = '';
            return $result;
        }
        $publicKey = '';
        $url = '';
        if (!self::transportResultValid($transportResult)) {
            $result['status'] = 'transport_indeterminate';
            $result['errors'] = ['transport_indeterminate'];
            return $result;
        }
        $result['networkAccess'] = $transportResult['networkAccess'];
        $result['providerContact'] = $transportResult['providerContact'];
        $result['httpStatus'] = $transportResult['statusCode'];
        $result['responseBytes'] = $transportResult['responseBytes'];
        $body = $transportResult['responseBody'];
        $transportResult['responseBody'] = '';
        if (!$transportResult['valid']
            || $transportResult['status'] !== 'response_received'
            || $result['httpStatus'] !== 200
            || $body === ''
            || strlen($body) !== $result['responseBytes']
        ) {
            $body = '';
            $result['status'] = 'response_refused';
            $result['errors'] = ['response_refused'];
            return $result;
        }
        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            $decoded = null;
        }
        $body = '';
        if (!is_array($decoded)) {
            $result['status'] = 'response_refused';
            $result['errors'] = ['response_refused'];
            return $result;
        }
        $projection =
            RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
                $plan,
                $decoded
            );
        $decoded = [];
        if (!RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::valid(
            $projection
        )) {
            $result['status'] = 'response_refused';
            $result['errors'] = ['response_refused'];
            return $result;
        }
        foreach ([
            'contracts', 'acceptanceTokenSha256',
            'personalAuthTokenSha256', 'contractsSha256',
            'responseEvidenceSha256', 'projectionSha256',
        ] as $key) {
            $result[$key] = $projection[$key];
        }
        $result['transportEvidenceSha256'] = self::hash([
            'schema' => 1,
            'purpose' => 'wompi-sandbox-merchant-contract-retrieval',
            'planSha256' => $result['planSha256'],
            'publicKeySha256' => $result['publicKeySha256'],
            'requestSha256' => $result['requestSha256'],
            'httpStatus' => $result['httpStatus'],
            'responseBytes' => $result['responseBytes'],
            'responseEvidenceSha256' => $result['responseEvidenceSha256'],
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
        ]);
        $result['valid'] = true;
        $result['status'] = 'merchant_contracts_retrieved';
        $result['errors'] = [];
        if (!self::valid($result)) {
            return self::result('projection_encoding_failed');
        }
        return $result;
    }

    public static function valid(array $result): bool
    {
        return array_keys($result) === array_keys(self::result(''))
            && ($result['valid'] ?? null) === true
            && ($result['status'] ?? null) === 'merchant_contracts_retrieved'
            && ($result['provider'] ?? null) === 'wompi'
            && ($result['environment'] ?? null) === 'sandbox'
            && ($result['operation'] ?? null)
                === 'merchant.acceptance-contracts.retrieve-sandbox'
            && self::sha256($result['planSha256'] ?? null)
            && self::sha256($result['publicKeySha256'] ?? null)
            && self::sha256($result['requestSha256'] ?? null)
            && is_array($result['contracts'] ?? null)
            && count($result['contracts']) === 2
            && self::sha256($result['acceptanceTokenSha256'] ?? null)
            && self::sha256($result['personalAuthTokenSha256'] ?? null)
            && self::sha256($result['contractsSha256'] ?? null)
            && self::sha256($result['responseEvidenceSha256'] ?? null)
            && self::sha256($result['projectionSha256'] ?? null)
            && self::sha256($result['transportEvidenceSha256'] ?? null)
            && ($result['httpStatus'] ?? null) === 200
            && is_int($result['responseBytes'] ?? null)
            && $result['responseBytes'] > 0
            && $result['responseBytes'] <= 65536
            && ($result['executionPerformed'] ?? null) === true
            && is_bool($result['networkAccess'] ?? null)
            && is_bool($result['providerContact'] ?? null)
            && ($result['responseBodyIncluded'] ?? null) === false
            && ($result['responseHeadersIncluded'] ?? null) === false
            && ($result['publicKeyIncluded'] ?? null) === false
            && ($result['rawTokensReturned'] ?? null) === false
            && ($result['providerMutation'] ?? null) === false
            && ($result['transactionCreation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['eventRegistration'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
            && ($result['errors'] ?? null) === [];
    }

    private static function transportResultValid($result): bool
    {
        return is_array($result)
            && array_keys($result) === [
                'valid', 'status', 'statusCode', 'responseBody',
                'responseBytes', 'networkAccess', 'providerContact',
                'providerMutation', 'transactionCreation', 'payment',
                'orderMutation', 'retryAuthorized', 'errors',
            ]
            && is_bool($result['valid'] ?? null)
            && is_string($result['status'] ?? null)
            && is_int($result['statusCode'] ?? null)
            && is_string($result['responseBody'] ?? null)
            && is_int($result['responseBytes'] ?? null)
            && is_bool($result['networkAccess'] ?? null)
            && is_bool($result['providerContact'] ?? null)
            && ($result['providerMutation'] ?? null) === false
            && ($result['transactionCreation'] ?? null) === false
            && ($result['payment'] ?? null) === false
            && ($result['orderMutation'] ?? null) === false
            && ($result['retryAuthorized'] ?? null) === false
            && is_array($result['errors'] ?? null);
    }

    private static function publicKeyValid(string $value): bool
    {
        return preg_match('/\Apub_test_[A-Za-z0-9]{16,128}\z/D', $value) === 1;
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

    private static function result(string $status): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'provider' => 'wompi',
            'environment' => 'sandbox',
            'operation' =>
                'merchant.acceptance-contracts.retrieve-sandbox',
            'planSha256' => '',
            'publicKeySha256' => '',
            'requestSha256' => '',
            'contracts' => null,
            'acceptanceTokenSha256' => '',
            'personalAuthTokenSha256' => '',
            'contractsSha256' => '',
            'responseEvidenceSha256' => '',
            'projectionSha256' => '',
            'transportEvidenceSha256' => '',
            'httpStatus' => 0,
            'responseBytes' => 0,
            'executionPerformed' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'publicKeyIncluded' => false,
            'rawTokensReturned' => false,
            'providerMutation' => false,
            'transactionCreation' => false,
            'payment' => false,
            'eventRegistration' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }
}

?>
