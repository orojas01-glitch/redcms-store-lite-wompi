<?php

declare(strict_types=1);

/** Registration-only C2 package. No registered handler is invoked here. */
require_once __DIR__ . '/WompiNequiRequestPlanner.php';
require_once __DIR__ . '/WompiNequiResponseGate.php';
require_once __DIR__ . '/WompiNequiSealedTransportDouble.php';
require_once __DIR__ . '/WompiSandboxEventVerifier.php';
require_once __DIR__ . '/WompiMerchantContractRequestPlanner.php';
require_once __DIR__ . '/WompiMerchantContractResponseGate.php';
require_once __DIR__ . '/WompiNequiOfflineAdapter.php';

return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-wompi/checkout',
        [
            'RED_CMS_Store_Lite_Wompi_Nequi_Offline_Adapter',
            'handle',
        ]
    );
    $registry->registerRoute(
        'redcms.store-lite-wompi/provider-events',
        static function (): never {
            throw new LogicException('c2_route_handler_not_operational');
        }
    );
};

?>
