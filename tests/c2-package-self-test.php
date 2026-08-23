<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
$coreDirectory = getenv('RED_CMS_CORE');
if (!is_string($coreDirectory) || $coreDirectory === '') {
    $coreDirectory = dirname($projectDirectory) . '/redcms v5.1';
}
foreach ([
    'addon_manifest_helpers.php',
    'addon_runtime_helpers.php',
    'addon_adapter_helpers.php',
    'addon_payment_adapter_preflight_helpers.php',
] as $helper) {
    $path = $coreDirectory . '/includes/' . $helper;
    if (!is_file($path)) {
        throw new RuntimeException(
            'Required RED-CMS helper not found; set RED_CMS_CORE: ' . $helper
        );
    }
    require_once $path;
}

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-wompi-c2-'
    . bin2hex(random_bytes(8));
$fixtureRoot = $temporaryRoot . '/project';
$fixturePackage = $fixtureRoot . '/addons/redcms/store-lite-wompi';
$packageId = 'redcms.store-lite-wompi';

function red_wompi_c2_package_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c2_package_copy(string $source, string $target): void
{
    if (!is_dir($target)
        && !mkdir($target, 0700, true)
        && !is_dir($target)
    ) {
        throw new RuntimeException('Could not create fixture package.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $source,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($destination)
                && !mkdir($destination, 0700, true)
                && !is_dir($destination)
            ) {
                throw new RuntimeException('Could not copy fixture directory.');
            }
            continue;
        }
        if (!copy($entry->getPathname(), $destination)) {
            throw new RuntimeException('Could not copy fixture file.');
        }
    }
}

function red_wompi_c2_package_remove(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

try {
    red_wompi_c2_package_copy(
        $projectDirectory . '/package',
        $fixturePackage
    );
    $package = red_addon_validate_manifest(
        $packageId,
        $fixtureRoot,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    red_wompi_c2_package_assert(
        !empty($package['valid'])
            && ($package['errors'] ?? null) === []
            && ($package['warnings'] ?? null) === [],
        'generic RED-CMS discovery validates the complete package'
    );
    red_wompi_c2_package_assert(
        ($package['id'] ?? null) === $packageId
            && ($package['manifest']['version'] ?? null) === '0.1.3'
            && ($package['manifest']['type'] ?? null) === 'adapter'
            && ($package['integrity']['declaredFiles'] ?? null) === 15
            && ($package['integrity']['verifiedFiles'] ?? null) === 15
            && ($package['integrity']['inventoryComplete'] ?? null) === true,
        'identity and fifteen-file integrity inventory are exact'
    );

    $manifest = $package['manifest'];
    red_wompi_c2_package_assert(
        ($manifest['provides']['adapters'] ?? null) === [
            $packageId . '/checkout',
        ]
            && ($manifest['dependencies']['required'] ?? null) === [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1.35 <1.0',
            ]]
            && ($manifest['outboundHosts'] ?? null) === [
                'sandbox.wompi.co',
            ],
        'one adapter, exact Store Lite dependency, and Sandbox host are fixed'
    );
    red_wompi_c2_package_assert(
        ($manifest['permissions'] ?? null) === []
            && ($manifest['publicMutationContracts'] ?? null) === []
            && ($manifest['jobs'] ?? null) === []
            && ($manifest['assets'] ?? null) === [
                'public' => [],
                'admin' => [],
            ],
        'package requests no permission, public mutation, job, or asset surface'
    );
    red_wompi_c2_package_assert(
        array_column($manifest['settings'], 'key') === [
            'wompi.public-key',
            'wompi.private-key',
            'wompi.integrity-key',
            'wompi.event-secret',
        ]
            && $manifest['settings'][0]['type'] === 'text'
            && $manifest['settings'][0]['secret'] === false
            && $manifest['settings'][0]['default'] === null,
        'one non-secret public-key setting is client-local and unset'
    );
    foreach (array_slice($manifest['settings'], 1) as $setting) {
        red_wompi_c2_package_assert(
            $setting['type'] === 'secret-reference'
                && $setting['secret'] === true
                && !array_key_exists('default', $setting),
            $setting['key'] . ' is an opaque reference with no default'
        );
    }
    red_wompi_c2_package_assert(
        ($manifest['routes'] ?? null) === [[
            'id' => $packageId . '/provider-events',
            'scope' => 'public',
            'path' => '/addons/redcms/store-lite-wompi/provider-events',
            'methods' => ['POST'],
            'authentication' => 'server-signature',
            'csrf' => 'not-applicable',
        ]],
        'one exact future event route is declared but not exposed'
    );
    red_wompi_c2_package_assert(
        count($manifest['migrations']) === 2
            && array_column($manifest['migrations'], 'id') === [
                '2026-08-23-wompi-payment-attempts',
                '2026-08-23-wompi-event-receipts',
            ],
        'two append-only package migrations are declared in order'
    );

    foreach ($manifest['integrity']['files'] as $file) {
        $path = $fixturePackage . '/' . $file['path'];
        red_wompi_c2_package_assert(
            is_file($path)
                && hash_equals($file['sha256'], hash_file('sha256', $path)),
            $file['path'] . ' matches its exact SHA-256'
        );
    }
    foreach ([
        'WompiNequiRequestPlanner.php',
        'WompiNequiResponseGate.php',
        'WompiNequiSealedTransportDouble.php',
        'WompiSandboxEventVerifier.php',
        'WompiMerchantContractRequestPlanner.php',
        'WompiMerchantContractResponseGate.php',
        'WompiContractConsentPresentation.php',
        'WompiContractConsentEvidence.php',
        'WompiNequiTransientWireRequestBuilder.php',
        'WompiTransactionResponseContainment.php',
        'WompiNequiOfflineAdapter.php',
    ] as $file) {
        red_wompi_c2_package_assert(
            hash_equals(
                hash_file('sha256', $projectDirectory . '/src/' . $file),
                hash_file('sha256', $fixturePackage . '/' . $file)
            ),
            $file . ' package copy is byte-identical to reviewed source'
        );
    }

    $registry = red_addon_runtime_register_package($package);
    $snapshot = $registry->snapshot();
    red_wompi_c2_package_assert(
        ($snapshot['packageId'] ?? null) === $packageId
            && ($snapshot['registrations']['adapters'] ?? null) === [
                $packageId . '/checkout',
            ]
            && ($snapshot['registrations']['routes'] ?? null) === [
                $packageId . '/provider-events',
            ],
        'generic contained registrar observes only declared adapter and route'
    );
    $handler = $registry->handler('adapters', $packageId . '/checkout');
    red_wompi_c2_package_assert(
        is_callable($handler),
        'registered adapter points to one callable reviewed handler'
    );
    $probe = red_addon_adapter_invoke_registered(
        $packageId . '/checkout',
        'contract.probe',
        [],
        $packageId,
        $handler,
        $manifest,
        null
    );
    red_wompi_c2_package_assert(
        ($probe['invoked'] ?? null) === true
            && ($probe['success'] ?? null) === true
            && ($probe['reason'] ?? null) === 'completed'
            && ($probe['data']['contractVersion'] ?? null)
                === 'colombia-c4b3-v1'
            && ($probe['data']['packageVersion'] ?? null) === '0.1.3'
            && ($probe['data']['merchantContractPreflightReady'] ?? null)
                === true
            && ($probe['data']['twoContractConsentReady'] ?? null) === true
            && ($probe['data']['transientWirePreflightReady'] ?? null)
                === true
            && ($probe['data']['responseContainmentReady'] ?? null) === true
            && ($probe['data']['transportReady'] ?? null) === false
            && ($probe['data']['secretResolution'] ?? null) === false
            && ($probe['data']['networkAccess'] ?? null) === false
            && ($probe['data']['providerContact'] ?? null) === false
            && ($probe['data']['payment'] ?? null) === false
            && ($probe['data']['orderMutation'] ?? null) === false,
        'offline typed contract probe returns only fixed false-effect facts'
    );
    $unsupported = red_addon_adapter_invoke_registered(
        $packageId . '/checkout',
        'checkout.create',
        [],
        $packageId,
        $handler,
        $manifest,
        null
    );
    red_wompi_c2_package_assert(
        ($unsupported['invoked'] ?? null) === true
            && ($unsupported['success'] ?? null) === false
            && ($unsupported['error'] ?? null)
                === 'provider_transport_disabled'
            && ($unsupported['reason'] ?? null) === 'adapter_error',
        'provider operation remains explicitly unsupported'
    );
    $route = $registry->handler(
        'routes',
        $packageId . '/provider-events'
    );
    $routeRefused = false;
    try {
        $route();
    } catch (LogicException $exception) {
        $routeRefused = $exception->getMessage()
            === 'c2_route_handler_not_operational';
    }
    red_wompi_c2_package_assert(
        $routeRefused,
        'declared provider-event route is a non-operational refusal'
    );

    $wompiProfile = red_addon_payment_adapter_profile($manifest);
    red_wompi_c2_package_assert(
        red_addon_payment_adapter_profile_is_valid($wompiProfile)
            && $wompiProfile['profileId']
                === 'store_lite_wompi_adapter_v1'
            && $wompiProfile['contractReady'] === true
            && $wompiProfile['activationSupported'] === false
            && $wompiProfile['packageExecution'] === false
            && $wompiProfile['secretResolution'] === false
            && $wompiProfile['networkAccess'] === false
            && $wompiProfile['routeExposure'] === false
            && $wompiProfile['errors'] === [],
        'current exact core profile accepts Wompi without executing it'
    );

    $attemptSql = file_get_contents(
        $fixturePackage
            . '/migrations/2026-08-23-create-payment-attempts.sql'
    );
    $eventSql = file_get_contents(
        $fixturePackage
            . '/migrations/2026-08-23-create-event-receipts.sql'
    );
    red_wompi_c2_package_assert(
        is_string($attemptSql)
            && is_string($eventSql)
            && str_contains(
                $attemptSql,
                'RED_Addon_StoreLite_Wompi_Payment_Attempts'
            )
            && str_contains(
                $eventSql,
                'RED_Addon_StoreLite_Wompi_Event_Receipts'
            )
            && str_contains($attemptSql, 'ENGINE=InnoDB')
            && str_contains($eventSql, 'ENGINE=InnoDB')
            && str_contains($eventSql, 'signed_event_and_lookup')
            && str_contains($eventSql, 'OccurredAt` + 90000'),
        'migrations declare only bounded InnoDB attempt and event evidence'
    );
    $sql = strtolower($attemptSql . "\n" . $eventSql);
    foreach ([
        'email', 'phone', 'acceptance_token', 'personal_auth', 'private_key',
        'integrity_key', 'event_secret', 'raw_body', 'response_body',
        'response_header', 'checkout_url',
    ] as $forbiddenColumn) {
        red_wompi_c2_package_assert(
            !str_contains($sql, $forbiddenColumn),
            'migration evidence excludes ' . $forbiddenColumn
        );
    }

    $source = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $fixturePackage,
            FilesystemIterator::SKIP_DOTS
        )
    );
    foreach ($iterator as $entry) {
        if ($entry->isFile()) {
            $source .= (string) file_get_contents($entry->getPathname());
        }
    }
    foreach ([
        'curl_', 'fsockopen', 'pfsockopen', 'stream_socket_client',
        'socket_create', 'socket_connect', 'file_get_contents("http',
        "file_get_contents('http", 'PDO', 'mysqli', '$_GET', '$_POST',
        '$_REQUEST', '$_SERVER', '$_COOKIE', '$_SESSION',
    ] as $forbiddenPrimitive) {
        red_wompi_c2_package_assert(
            !str_contains($source, $forbiddenPrimitive),
            'package excludes primitive ' . $forbiddenPrimitive
        );
    }
    red_wompi_c2_package_assert(
        !preg_match(
            '/(?:prv|pub)_(?:test|prod)_[A-Za-z0-9]{8,}/',
            $source
        )
            && !preg_match(
                '/(?:test|prod)_(?:events|integrity)_[A-Za-z0-9]{8,}/',
                $source
            ),
        'package contains no credential-shaped Wompi value'
    );

    red_wompi_c2_package_remove($temporaryRoot);
    red_wompi_c2_package_assert(
        !file_exists($temporaryRoot),
        'temporary package fixture is removed exactly'
    );
    echo 'Wompi C2 package self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_wompi_c2_package_remove($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
