# Colombia C2 Acceptance

Status: historical C2 baseline passed at package `0.1.0`. Version `0.1.5`
retains the 34-assertion offline contract and the later C4B1/C4B2/C4B3/C4B4A pure
preflights.

## Focused results

- Offline provider contract: 34 assertions.
- Current package, integrity, registrar, migrations, twelve-file source parity,
  exact current-core Wompi profile, and cleanup: 74 assertions.
- C4B1 merchant-contract preflight: 29 assertions.
- C4B2 presentation/consent/wire preflight: 49 assertions.
- C4B3 create/lookup response containment: 48 assertions.
- C4B4A no-contact attempt contract: 52 assertions.
- Current total: 286 assertions.
- PHP lint: all fifteen source files, all fifteen package copies, package
  registrar, and all six tests pass.

## Required evidence

- The exact request plan is COP/Nequi/Sandbox-only, self-fingerprinted, and
  records wire construction, secret resolution, provider/network effects,
  payment, webhook, browser, Store Lite mutation, and retry false.
- The exact PENDING projection adopts the merged C1
  `out_of_band_confirmation` result and contains no URL.
- Wrong currency, missing acceptance, missing secret-reference availability,
  raw personal fields, final initiation status, amount/order/method mismatch,
  extra URL, changed plan, and replay fail closed.
- Dynamic signed-event properties, 25-hour retry-compatible timing, checksum,
  exact lookup agreement, final outcomes, replay, environment, and mismatch
  cases pass with mutation and payment application false.
- Generic RED-CMS discovery verifies all nineteen declared payload hashes and no
  undeclared file.
- Package/source copies are byte-identical.
- The registrar observes only the declared adapter and refusing event route.
- `contract.probe` returns fixed non-secret false-effect facts; provider
  operation returns `provider_transport_disabled`.
- Current exact payment-profile validation accepts the package only as
  `store_lite_wompi_adapter_v1`, with execution, secret, network, activation,
  and route effects false.
- Both migrations are InnoDB evidence tables and contain no personal,
  credential, raw-event, body/header, or checkout-URL columns.
- Package source contains no database, request-global, credential-shaped,
  cURL, socket, or HTTP-reading primitive.
- Temporary package fixture cleanup passes exactly.

## Explicit non-effects

No RED-CMS core file, Store Lite file, Stripe adapter file, database, package
installation, migration execution, enablement, runtime publication, route
exposure, real setting, secret value, provider account, DNS, TLS, HTTP, Wompi
transaction, Nequi notification, payment, webhook ingress, browser flow, Store
Lite mutation, hosted-demo change, client data, or deployment is created by C2.

The C4B1 evidence and current non-effects are recorded separately in
[`COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md`](COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md).
The C4B2 evidence is in
[`COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md`](COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md).
The C4B3 evidence is in
[`COLOMBIA-C4B3-RESPONSE-CONTAINMENT.md`](COLOMBIA-C4B3-RESPONSE-CONTAINMENT.md).
The C4B4A evidence is in
[`COLOMBIA-C4B4A-NO-CONTACT-ATTEMPT.md`](COLOMBIA-C4B4A-NO-CONTACT-ATTEMPT.md).
