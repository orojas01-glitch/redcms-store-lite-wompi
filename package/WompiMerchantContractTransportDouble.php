<?php

declare(strict_types=1);

/** Sealed no-network C4C1 double for the merchant-contract transport. */
final class RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double
    implements RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport
{
    private string $mode;
    private int $callCount = 0;
    private string $requestSha256 = '';

    public function __construct(string $mode = 'completed')
    {
        if (!in_array($mode, ['completed', 'fault', 'malformed'], true)) {
            throw new InvalidArgumentException('Invalid double mode.');
        }
        $this->mode = $mode;
    }

    public function get(string $url, int $responseMaxBytes): array
    {
        $this->callCount++;
        $this->requestSha256 = hash(
            'sha256',
            json_encode(
                [$url, $responseMaxBytes],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
        if ($this->mode === 'fault') {
            throw new RuntimeException('merchant_transport_double_fault');
        }
        if ($this->mode === 'malformed') {
            return ['valid' => true, 'statusCode' => 200];
        }
        $body = json_encode(
            [
                'data' => [
                    'presigned_acceptance' => [
                        'acceptance_token' =>
                            'synthetic.end.user.' . str_repeat('a', 32),
                        'permalink' =>
                            'https://contracts.wompi.co/synthetic-end-user.pdf',
                        'type' => 'END_USER_POLICY',
                    ],
                    'presigned_personal_data_auth' => [
                        'acceptance_token' =>
                            'synthetic.personal.auth.' . str_repeat('b', 32),
                        'permalink' =>
                            'https://contracts.wompi.com/synthetic-personal-data.pdf',
                        'type' => 'PERSONAL_DATA_AUTH',
                    ],
                ],
            ],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        return [
            'valid' => true,
            'status' => 'response_received',
            'statusCode' => 200,
            'responseBody' => $body,
            'responseBytes' => strlen($body),
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'transactionCreation' => false,
            'payment' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    public function requestSha256(): string
    {
        return $this->requestSha256;
    }
}

?>
