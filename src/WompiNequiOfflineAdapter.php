<?php

declare(strict_types=1);

/** Registration-only C4B1 adapter. Provider operations remain unavailable. */
final class RED_CMS_Store_Lite_Wompi_Nequi_Offline_Adapter
{
    public static function handle($request)
    {
        if (!$request instanceof RED_Addon_Adapter_Request) {
            return RED_Addon_Adapter_Result::failure('request_invalid');
        }
        if ($request->operation() !== 'contract.probe'
            || $request->input() !== []
        ) {
            return RED_Addon_Adapter_Result::failure(
                'provider_transport_disabled'
            );
        }
        return RED_Addon_Adapter_Result::success([
            'contractVersion' => 'colombia-c4b1-v1',
            'provider' => 'wompi',
            'method' => 'nequi',
            'currency' => 'COP',
            'environment' => 'sandbox',
            'initiationMode' => 'out_of_band_confirmation',
            'packageVersion' => '0.1.1',
            'merchantContractPreflightReady' => true,
            'transportReady' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
        ]);
    }
}

?>
