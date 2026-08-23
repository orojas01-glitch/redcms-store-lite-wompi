<?php

declare(strict_types=1);

/** Registration-only C4B4A adapter. Provider operations remain unavailable. */
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
            'contractVersion' => 'colombia-c4b4a-v1',
            'provider' => 'wompi',
            'method' => 'nequi',
            'currency' => 'COP',
            'environment' => 'sandbox',
            'initiationMode' => 'out_of_band_confirmation',
            'packageVersion' => '0.1.4',
            'merchantContractPreflightReady' => true,
            'twoContractConsentReady' => true,
            'transientWirePreflightReady' => true,
            'responseContainmentReady' => true,
            'noContactAttemptContractReady' => true,
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
