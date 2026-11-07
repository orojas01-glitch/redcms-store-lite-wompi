# Wompi W1 Web Checkout (offline)

Status: offline package gate, version `0.2.0`. On 2026-11-07 the owner chose
Wompi Web Checkout (hosted page) for the first launch instead of the direct
Nequi API. The Nequi API contracts from C2 to C4C1 stay in the package,
unchanged, as a later option.

Nothing in this gate contacts Wompi, resolves a real key, opens a database,
publishes a route or changes an order.

## What W1 adds

| File | Purpose |
| --- | --- |
| `WompiEnvironment.php` | Resolves `sandbox` or `production` from the key prefixes (`pub_test_`/`pub_prod_`, `prv_`, `test_integrity_`/`prod_integrity_`, `test_events_`/`prod_events_`) and refuses any mix. Returns API base, API host, event environment (`test`/`prod`) and the checkout action, never a key. |
| `WompiWebCheckoutPlanner.php` | Builds the exact GET form for `https://checkout.wompi.co/p/` from a server-side order projection: `public-key`, `currency=COP`, `amount-in-cents`, `reference` (the Store Lite `ord_` id), `signature:integrity`, `redirect-url`, optional `expiration-time` (5 minutes to 24 hours ahead). Computes the integrity signature server-side and never returns the integrity secret. Includes an escaped, script-free HTML form renderer. |
| `WompiEventVerifier.php` | Verifies a raw `transaction.updated` body for Sandbox or production: checksum of the `signature.properties` values + `timestamp` + events secret (case-insensitive hex, constant-time), optional `X-Event-Checksum` header agreement, environment match, 25-hour age window, replay refusal. Projects the transaction to six fields and discards customer data. `reconcile()` then requires a server-side lookup to agree and the stored order to match reference, amount and currency before producing a Store Lite `payment.event.apply` event. |

Status mapping: `APPROVED` to `paid`, `DECLINED` and `ERROR` to `failed`,
`VOIDED` to `cancelled`, `PENDING` is verified but not final (acknowledge,
change nothing). The Store Lite payment method is `wompi_checkout`.

## Provider facts (checked 2026-11-07)

- Integrity signature: SHA-256 of `reference + amount_in_cents + currency
  [+ expiration_time] + integrity_secret`; the documented example is a test
  vector. [Widget & Web Checkout](https://docs.wompi.co/docs/colombia/widget-checkout-web/)
- Event checksum: SHA-256 of the values named in `signature.properties`, then
  `timestamp`, then the events secret; also sent as `X-Event-Checksum`. The
  documented example checksum is uppercase. Wompi retries non-200 responses
  about 30 minutes, 3 hours and 24 hours later. [Eventos](https://docs.wompi.co/docs/colombia/eventos/)
- Web Checkout appends `?id=<transaction id>` to the redirect URL. The return
  page is informational only.
- The docs give no way to limit the hosted page to one payment method.

## Known follow-ups outside this package

1. **Store Lite:** add `wompi_checkout` to the hosted payment methods in
   `PaymentEventTransition` and let guest checkout create a pending hosted
   order for it.
2. **Core initiation contract:** `hosted_redirect` accepts only a query-free
   checkout URL, so it cannot carry Web Checkout fields. Add a closed
   `hosted_get_form` mode (fixed action host, exact field names) or a
   RED-CMS-owned redirect route.
3. **Core ingress:** wire the declared `provider-events` route to
   `WompiEventVerifier`, a server-side transaction lookup (needs a bounded
   GET transport and `production.wompi.co` in `outboundHosts`), and the Store
   Lite payment-event service; record evidence before applying; return 200.
4. **Core version pins:** the C4C merchant-read helpers pin package `0.1.5`
   and will refuse `0.2.0`. That path serves the deferred Nequi API flow.

## Run

```sh
PHP_CLI=/path/to/php RED_CMS_CORE=/path/to/redcms scripts/test.sh
```

W1 adds 63 assertions; the full suite passes with PHP 8.3 against core
`41ded30`.
