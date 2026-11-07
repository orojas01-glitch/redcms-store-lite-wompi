<?php

declare(strict_types=1);

/**
 * Pure W1 environment profile for Wompi Web Checkout.
 *
 * Resolves one environment (sandbox or production) from the shapes of the
 * four client-local key values and refuses any mix, so Sandbox keys can never
 * be used against production endpoints or production events and vice versa.
 * It never returns, logs, or stores a key value.
 */
final class RED_CMS_Store_Lite_Wompi_Environment
{
    public const SANDBOX = 'sandbox';
    public const PRODUCTION = 'production';

    private const PROFILES = [
        self::SANDBOX => [
            'publicPrefix' => 'pub_test_',
            'privatePrefix' => 'prv_test_',
            'integrityPrefix' => 'test_integrity_',
            'eventsPrefix' => 'test_events_',
            'apiBase' => 'https://sandbox.wompi.co/v1',
            'apiHost' => 'sandbox.wompi.co',
            'eventEnvironment' => 'test',
        ],
        self::PRODUCTION => [
            'publicPrefix' => 'pub_prod_',
            'privatePrefix' => 'prv_prod_',
            'integrityPrefix' => 'prod_integrity_',
            'eventsPrefix' => 'prod_events_',
            'apiBase' => 'https://production.wompi.co/v1',
            'apiHost' => 'production.wompi.co',
            'eventEnvironment' => 'prod',
        ],
    ];

    public const CHECKOUT_ACTION = 'https://checkout.wompi.co/p/';

    /**
     * @param string $expected the environment the installation is configured
     *        for; every supplied key must belong to it.
     * @param array<string, string> $keys any subset of public, private,
     *        integrity and events values that the caller needs for this call.
     */
    public static function resolve(string $expected, array $keys): array
    {
        $result = [
            'valid' => false,
            'environment' => '',
            'apiBase' => '',
            'apiHost' => '',
            'eventEnvironment' => '',
            'checkoutAction' => '',
            'errors' => [],
        ];
        if (!array_key_exists($expected, self::PROFILES)) {
            $result['errors'] = ['environment_unknown'];
            return $result;
        }
        if ($keys === []) {
            $result['errors'] = ['keys_missing'];
            return $result;
        }
        $profile = self::PROFILES[$expected];
        $prefixes = [
            'public' => $profile['publicPrefix'],
            'private' => $profile['privatePrefix'],
            'integrity' => $profile['integrityPrefix'],
            'events' => $profile['eventsPrefix'],
        ];
        foreach ($keys as $name => $value) {
            if (!is_string($name) || !array_key_exists($name, $prefixes)) {
                $result['errors'] = ['key_name_invalid'];
                return $result;
            }
            if (!self::keyShapeValid($value, $prefixes[$name])) {
                $result['errors'] = [$name . '_key_environment_mismatch'];
                return $result;
            }
        }
        $result['valid'] = true;
        $result['environment'] = $expected;
        $result['apiBase'] = $profile['apiBase'];
        $result['apiHost'] = $profile['apiHost'];
        $result['eventEnvironment'] = $profile['eventEnvironment'];
        $result['checkoutAction'] = self::CHECKOUT_ACTION;
        return $result;
    }

    public static function eventEnvironment(string $environment): string
    {
        return self::PROFILES[$environment]['eventEnvironment'] ?? '';
    }

    private static function keyShapeValid(mixed $value, string $prefix): bool
    {
        return is_string($value)
            && str_starts_with($value, $prefix)
            && strlen($value) > strlen($prefix) + 7
            && strlen($value) <= 200
            && preg_match('/\A[A-Za-z0-9_]+\z/D', $value) === 1;
    }
}

?>
