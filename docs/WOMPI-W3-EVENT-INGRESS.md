# Wompi W3 event ingress coordinator (offline)

Status: offline package gate, version `0.3.0`. Nothing here publishes a route,
resolves a key, opens a database or contacts Wompi.

`RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator::handle()` takes one raw
`transaction.updated` delivery and decides everything the provider-events route
must do with it. All side effects are injected callables, so core supplies the
real HTTP lookup, order read and Store Lite call later:

| Callable | Supplied by core | Contract |
| --- | --- | --- |
| `$lookup(string $url): ?string` | bounded GET transport | Raw body of `GET {api}/v1/transactions/{id}`; the URL is built here from the environment profile and the signed transaction id only. |
| `$loadOrder(string $orderId): ?array` | Store Lite order read | The Store Lite expected-order projection (9 keys), or `null` when unknown. |
| `$apply(array $order, array $event): array` | Store Lite `commerce.orders/payment.event.apply` | `['ok' => true, 'status' => applied/replayed/unchanged]` or `['ok' => false, 'error' => code]`. |

## Decision table

| Situation | HTTP | Disposition | Store Lite called |
| --- | --- | --- | --- |
| Verified, lookup agrees, order matches, final status | 200 | `applied` / `replayed` / `unchanged` | yes |
| Verified `PENDING` | 200 | `pending` | no |
| Same delivery seen before | 200 | `replayed` | no |
| Bad checksum, malformed body, wrong environment, too old | 401 | `event_refused` | no |
| Events secret does not fit the configured environment | 503 | `configuration_invalid` | no |
| Lookup unreachable, malformed, or disagrees with the event | 503 | `lookup_*` | no |
| Order unknown | 200 | `order_unknown` | no |
| Order amount, currency, reference or payment method differs | 200 | `order_mismatch` | no |
| Order read failed or malformed | 503 | `order_unavailable` | no |
| Store Lite storage down or state conflict | 503 | `apply_unavailable` | yes |
| Store Lite refused the transition or replay conflict | 200 | `apply_refused` | yes |

503 means "Wompi, retry later" (Wompi retries non-200 deliveries about 30
minutes, 3 hours and 24 hours later). 200 means the delivery is final for this
order even when nothing changed, so a mismatched or unknown order is not
retried forever; core must record those dispositions for the operator.

The result never contains the raw body, customer data or a secret: only the
status, disposition, outcome, event evidence hash, transaction id, order id and
an error code.

## Tests

`tests/w3-event-ingress-self-test.php`: 31 assertions covering every row above
with recording doubles, including that a forgery triggers no lookup, that the
lookup URL is the exact Sandbox transaction URL, and that no customer email or
secret leaves the coordinator.

## Still outside this package

1. Core route wiring: capture the POST with the existing server-event ingress
   helper, resolve the events secret, pass a bounded GET transport, the Store
   Lite order read and service call, record the disposition, and emit the
   status.
2. `production.wompi.co` in `outboundHosts` (only `sandbox.wompi.co` today).
3. The first Sandbox transaction and signed event (needs the owner's keys and
   authorization).
