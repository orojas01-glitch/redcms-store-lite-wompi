<?php

declare(strict_types=1);

/** C4C1 adapter with one bounded read-only Sandbox merchant operation. */
final class RED_CMS_Store_Lite_Wompi_Nequi_Offline_Adapter
{
    public static function handle($request)
    {
        if (!$request instanceof RED_Addon_Adapter_Request) {
            return RED_Addon_Adapter_Result::failure('request_invalid');
        }
        if ($request->operation() ===
                'merchant.acceptance-contracts.retrieve-sandbox'
            && is_array($request->input())
            && array_keys($request->input()) === ['plan', 'publicKey']
            && is_array($request->input()['plan'] ?? null)
            && is_string($request->input()['publicKey'] ?? null)
        ) {
            $result =
                RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
                    $request->input()['plan'],
                    $request->input()['publicKey'],
                    new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Curl_Transport()
                );
            return RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::valid(
                $result
            )
                ? RED_Addon_Adapter_Result::success($result)
                : RED_Addon_Adapter_Result::failure(
                    is_string($result['status'] ?? null)
                        ? $result['status']
                        : 'retrieval_failed'
                );
        }
        if ($request->operation() !== 'contract.probe'
            || $request->input() !== []
        ) {
            return RED_Addon_Adapter_Result::failure(
                'provider_transport_disabled'
            );
        }
        return RED_Addon_Adapter_Result::success([
            'contractVersion' => 'colombia-c4c1-v1',
            'provider' => 'wompi',
            'method' => 'nequi',
            'currency' => 'COP',
            'environment' => 'sandbox',
            'initiationMode' => 'out_of_band_confirmation',
            'packageVersion' => '0.1.5',
            'merchantContractPreflightReady' => true,
            'twoContractConsentReady' => true,
            'transientWirePreflightReady' => true,
            'responseContainmentReady' => true,
            'noContactAttemptContractReady' => true,
            'merchantContractReadOnlyReady' => true,
            'transactionTransportReady' => false,
            'transportReady' => true,
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
