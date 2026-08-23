# RED-CMS Store Lite Wompi Adapter

This repository is the separately distributed Wompi payment adapter candidate
for RED-CMS Store Lite. Version `0.1.1` completes Colombia C4B1 as a
credential-free, no-contact merchant-contract preflight while preserving the
offline C2 transaction/event skeleton. Its initial commercial contract is only
one-time Nequi payments in `COP` through the provider-neutral
`out_of_band_confirmation` initiation mode.

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
  contract links plus token/evidence hashes, never raw acceptance tokens; and
- one registration-only typed adapter exposing only `contract.probe` while
  every provider operation returns `provider_transport_disabled`.

## Current core compatibility

Generic RED-CMS discovery and contained runtime registration accept the exact
package and all eleven integrity files. The exact current Wompi core profile
accepts the manifest as `store_lite_wompi_adapter_v1` while package execution,
secret resolution, network access, route exposure, and activation remain
false. This replaces the historical C2 assertion that the then-Stripe-only
profile refused Wompi; C3A intentionally superseded that expectation.

The C4B1 classes are source-level pure contracts, not adapter operations. They
do not construct a final path, resolve the public key or any secret, open a
connection, persist a provider response, present a contract, record customer
consent, or create a transaction. C4B2 and later gates remain separately
reviewed.

RED-CMS core's published-package C3A fixture and disposable lifecycle scripts
remain pinned to package `0.1.0`/commit `e17a371`. After `0.1.1` is published,
a separate core adoption gate must update only those exact package identity/
integrity pins and rerun the clean disposable proofs. This package change does
not silently update or install any RED-CMS client.

## Run the offline proof

```sh
PHP_CLI=/path/to/php RED_CMS_CORE=/path/to/redcms scripts/test.sh
```

The current focused suite passes 34 C2 provider-contract assertions, 64
package/current-core assertions, and 29 C4B1 merchant-contract assertions. See
[`docs/COLOMBIA-C2-PACKAGE-SKELETON.md`](docs/COLOMBIA-C2-PACKAGE-SKELETON.md)
and
[`docs/COLOMBIA-C2-ACCEPTANCE.md`](docs/COLOMBIA-C2-ACCEPTANCE.md)
and
[`docs/COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md`](docs/COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md).
