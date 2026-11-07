<?php

declare(strict_types=1);

require_once __DIR__ . '/WompiEnvironment.php';

/**
 * Pure W1 Wompi Web Checkout form planner.
 *
 * Builds the exact GET form fields for https://checkout.wompi.co/p/ from a
 * server-derived Store Lite order projection. The integrity signature is
 * computed here, on the server, from the injected integrity secret; the
 * secret itself is never returned. The signature is safe to send to the
 * browser by design: it only binds reference, amount, currency and expiry.
 *
 * Creating the form does not create a transaction and never marks an order
 * paid. Only a verified transaction.updated event that agrees with a
 * server-side transaction lookup may do that.
 */
final class RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner
{
    public const CURRENCY = 'COP';
    private const MIN_AMOUNT = 100;
    private const MAX_AMOUNT = 999999999999;
    private const MIN_EXPIRY_SECONDS = 300;
    private const MAX_EXPIRY_SECONDS = 86400;

    /**
     * @param array{orderId: string, amountMinor: int, currency: string} $order
     */
    public static function plan(
        string $environment,
        string $publicKey,
        string $integritySecret,
        array $order,
        string $redirectUrl,
        ?string $expirationTime,
        int $now
    ): array {
        $result = self::result();
        $profile = RED_CMS_Store_Lite_Wompi_Environment::resolve(
            $environment,
            ['public' => $publicKey, 'integrity' => $integritySecret]
        );
        if ($profile['valid'] !== true) {
            $result['errors'] = $profile['errors'];
            return $result;
        }
        if (array_keys($order) !== ['orderId', 'amountMinor', 'currency']
            || !self::orderId($order['orderId'] ?? null)
            || !is_int($order['amountMinor'] ?? null)
            || $order['amountMinor'] < self::MIN_AMOUNT
            || $order['amountMinor'] > self::MAX_AMOUNT
            || ($order['currency'] ?? null) !== self::CURRENCY
        ) {
            $result['errors'] = ['order_invalid'];
            return $result;
        }
        if (!self::redirectUrlValid($redirectUrl)) {
            $result['errors'] = ['redirect_url_invalid'];
            return $result;
        }
        if ($expirationTime !== null
            && !self::expirationValid($expirationTime, $now)
        ) {
            $result['errors'] = ['expiration_time_invalid'];
            return $result;
        }

        $reference = $order['orderId'];
        $amount = (string) $order['amountMinor'];
        $signature = self::integritySignature(
            $reference,
            $amount,
            self::CURRENCY,
            $expirationTime,
            $integritySecret
        );

        $fields = [
            ['public-key', $publicKey],
            ['currency', self::CURRENCY],
            ['amount-in-cents', $amount],
            ['reference', $reference],
            ['signature:integrity', $signature],
            ['redirect-url', $redirectUrl],
        ];
        if ($expirationTime !== null) {
            $fields[] = ['expiration-time', $expirationTime];
        }

        $result['valid'] = true;
        $result['environment'] = $profile['environment'];
        $result['action'] = $profile['checkoutAction'];
        $result['reference'] = $reference;
        $result['amountMinor'] = $order['amountMinor'];
        $result['fields'] = $fields;
        $result['planSha256'] = hash(
            'sha256',
            'redcms.wompi.web-checkout.plan.v1|'
                . json_encode($fields, JSON_UNESCAPED_SLASHES)
        );
        return $result;
    }

    /**
     * Wompi integrity signature: lowercase hex SHA-256 of
     * reference . amount_in_cents . currency [. expiration_time] . secret.
     */
    public static function integritySignature(
        string $reference,
        string $amountInCents,
        string $currency,
        ?string $expirationTime,
        string $integritySecret
    ): string {
        return hash(
            'sha256',
            $reference
                . $amountInCents
                . $currency
                . ($expirationTime ?? '')
                . $integritySecret
        );
    }

    /** Renders the plan as an auto-escaped HTML GET form (no script). */
    public static function formHtml(array $plan, string $buttonLabel): string
    {
        if (($plan['valid'] ?? null) !== true
            || !is_array($plan['fields'] ?? null)
        ) {
            return '';
        }
        $escape = static fn (string $value): string => htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );
        $html = '<form class="red-wompi-checkout" method="GET" action="'
            . $escape((string) $plan['action']) . '">';
        foreach ($plan['fields'] as [$name, $value]) {
            $html .= '<input type="hidden" name="' . $escape($name)
                . '" value="' . $escape($value) . '">';
        }
        $html .= '<button type="submit">' . $escape($buttonLabel)
            . '</button></form>';
        return $html;
    }

    private static function result(): array
    {
        return [
            'valid' => false,
            'environment' => '',
            'action' => '',
            'method' => 'GET',
            'reference' => '',
            'amountMinor' => 0,
            'fields' => [],
            'planSha256' => '',
            'transactionCreated' => false,
            'paymentVerified' => false,
            'orderMutationAuthorized' => false,
            'errors' => [],
        ];
    }

    private static function orderId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Aord_[a-f0-9]{32}\z/D', $value) === 1;
    }

    private static function redirectUrlValid(string $value): bool
    {
        if ($value === ''
            || strlen($value) > 2048
            || preg_match('/[\x00-\x20\x7F"<>\\\\]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && is_string($url['host'] ?? null)
            && preg_match(
                '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+'
                    . '[a-z]{2,63}\z/D',
                $url['host']
            ) === 1
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && !array_key_exists('fragment', $url)
            && is_string($url['path'] ?? null)
            && str_starts_with($url['path'], '/');
    }

    private static function expirationValid(string $value, int $now): bool
    {
        if (preg_match(
            '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z\z/D',
            $value
        ) !== 1) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.v\Z',
            $value,
            new DateTimeZone('UTC')
        );
        if (!$parsed instanceof DateTimeImmutable
            || $parsed->format('Y-m-d\TH:i:s.v\Z') !== $value
        ) {
            return false;
        }
        $delta = $parsed->getTimestamp() - $now;
        return $delta >= self::MIN_EXPIRY_SECONDS
            && $delta <= self::MAX_EXPIRY_SECONDS;
    }
}

?>
