<?php

declare(strict_types=1);

/** One-use in-memory C2 transport double with no network primitive. */
final class RED_CMS_Store_Lite_Wompi_Nequi_Sealed_Transport_Double
{
    private string $planSha256;
    private array $response;
    private bool $used = false;

    public function __construct(array $plan, array $response)
    {
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan)) {
            throw new InvalidArgumentException('c2_plan_invalid');
        }
        $this->planSha256 = self::hash($plan);
        $this->response = $response;
    }

    public function execute(array $plan): array
    {
        if ($this->used) {
            return self::refusal('transport_double_replayed');
        }
        $this->used = true;
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan)
            || !hash_equals($this->planSha256, self::hash($plan))
        ) {
            $this->response = [];
            return self::refusal('transport_double_plan_changed');
        }
        $response = $this->response;
        $this->response = [];
        $result = RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::accept(
            $plan,
            $response
        );
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Response_Gate::valid($result)) {
            return self::refusal('transport_double_response_refused');
        }
        return $result;
    }

    private static function refusal(string $reason): array
    {
        return [
            'valid' => false,
            'status' => $reason,
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
            'errors' => [$reason],
        ];
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
