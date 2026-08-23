# RED-CMS Store Lite Wompi Adapter

This repository is the separately distributed Wompi payment adapter candidate
for RED-CMS Store Lite. Version `0.1.3` completes Colombia C4B3 as a
credential-free, no-contact transaction-create and lookup response-containment
boundary while preserving the offline C2/C4B1/C4B2 contracts. Its initial
commercial contract is only one-time Nequi payments in `COP` through the
provider-neutral `out_of_band_confirmation` initiation mode.

The package is not installed, enabled, or connected to RED-CMS runtime. It has
no Wompi account, key value, customer data, network client, executable provider
operation, public webhook ingress, browser flow, payment, Store Lite mutation,
demo activation, or deployment.

## Current offline contents

- one generic RED-CMS adapter identity:
  `redcms.store-lite-wompi/checkout`;
- exact Store Lite dependency `>=0.1.35 <1.0`;
- one unset client-local non-secret `wompi.public-key` setting;
- three secret-reference settings with no values or defaults;
- Sandbox-only declared outbound host `sandbox.wompi.co`;
- one future server-signature event route whose handler always refuses;
- two unexecuted InnoDB migrations for bounded payment-attempt and event-
  receipt evidence with no email, phone, token, key, raw body, response body,
  response header, or checkout URL columns;
- one hashed non-executable transaction request planner;
- one strict synthetic PENDING-response gate that adopts RED-CMS C1 exactly;
- one one-use sealed in-memory transport double;
- one dynamic-property, 25-hour retry-compatible Sandbox event verifier that
  requires signed-event and lookup agreement;
- one pure merchant-contract request planner that fixes Sandbox
  `GET /v1/merchants/{public_key}` while receiving only public-key availability
  and hash evidence;
- one strict synthetic merchant-response gate that returns exactly two HTTPS
  contract links plus token/evidence hashes, never raw acceptance tokens;
- one exact two-link/two-required-control presentation model with no HTML,
  token, browser, or consent side effect;
- one 15-minute explicit two-contract consent evidence boundary bound to order,
  subject, contract/token hashes, and nonce;
- one transient Sandbox-only Nequi wire/signature preflight that constructs the
  exact body/header internally, hashes it, discards it, and returns no raw
  personal, token, credential, signature, or wire value;
- one strict create/lookup response-containment boundary that accepts only
  bounded documented Sandbox projections, discards personal/provider detail,
  and treats even APPROVED as a proposed outcome without payment verification
  or Store Lite mutation authority; and
- one registration-only typed adapter exposing only `contract.probe` while
  every provider operation returns `provider_transport_disabled`.

## Current core compatibility

Generic RED-CMS discovery and contained runtime registration accept the exact
package and all fifteen integrity files. The exact current Wompi core profile
accepts the manifest as `store_lite_wompi_adapter_v1` while package execution,
secret resolution, network access, route exposure, and activation remain
false. This replaces the historical C2 assertion that the then-Stripe-only
profile refused Wompi; C3A intentionally superseded that expectation.

The C4B1/C4B2/C4B3 classes are source-level pure contracts, not adapter
operations. They do not resolve a public key or secret, open a connection,
return/persist a wire request, or create a transaction. The C4B2 preflight
constructs one exact synthetic body/header/signature only inside a pure call,
then returns domain-separated and double-hashed evidence with every external/
business effect false.
C4B3 accepts already-captured arrays only. It performs no HTTP, cannot verify a
payment, and cannot authorize an order mutation.

RED-CMS core's published-package fixture and disposable lifecycle scripts
adopted package `0.1.2` at core commit `805f35e`. After `0.1.3` is published, a
separate core adoption gate must update only those exact package identity/
integrity pins and rerun the clean disposable proofs. This package change does
not silently update or install any RED-CMS client.

## Run the offline proof

```sh
PHP_CLI=/path/to/php RED_CMS_CORE=/path/to/redcms scripts/test.sh
```

The current focused suite passes 34 C2 provider-contract assertions, 72
package/current-core assertions, 29 C4B1 merchant-contract assertions, and 49
C4B2 consent/wire assertions, plus 48 C4B3 response-containment assertions. See
[`docs/COLOMBIA-C2-PACKAGE-SKELETON.md`](docs/COLOMBIA-C2-PACKAGE-SKELETON.md)
and
[`docs/COLOMBIA-C2-ACCEPTANCE.md`](docs/COLOMBIA-C2-ACCEPTANCE.md)
and
[`docs/COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md`](docs/COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md)
and
[`docs/COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md`](docs/COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md)
and
[`docs/COLOMBIA-C4B3-RESPONSE-CONTAINMENT.md`](docs/COLOMBIA-C4B3-RESPONSE-CONTAINMENT.md).
