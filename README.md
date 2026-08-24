# RED-CMS Store Lite Wompi Adapter

This repository is the separately distributed Wompi payment adapter candidate
for RED-CMS Store Lite. Version `0.1.5` adds the C4C1 implementation of one
bounded read-only Sandbox merchant-contract retrieval while preserving the
C2/C4B1/C4B2/C4B3/C4B4A contracts. Its initial commercial contract remains
only one-time Nequi payments in `COP` through the provider-neutral
`out_of_band_confirmation` initiation mode.

The package is not installed, enabled, or connected to a client. The new
network client was proved only through a sealed no-network double and has not
been invoked. No Wompi account/dashboard, key value, customer data, provider
request, public webhook ingress, browser flow, transaction, payment, Store
Lite mutation, demo activation, or deployment occurred.

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
  or Store Lite mutation authority;
- one pure sealed-double-only attempt contract that binds authorization and
  first-claim preparation to C2/C4B2 identities and C4B3 observations while
  explicitly keeping durable claim persistence, replay protection, execution,
  provider contact, and order mutation unavailable; and
- one typed adapter exposing `contract.probe` plus exactly one C4C1 read-only
  Sandbox merchant-contract operation. The transport fixes HTTPS GET, TLS
  verification, no redirects/proxy, strict time/size ceilings, response
  containment, and no retry; transaction and event operations remain disabled.

## Current core compatibility

Generic RED-CMS discovery and contained runtime registration accept the exact
package and all nineteen integrity files. The exact current Wompi core profile
accepts the manifest as `store_lite_wompi_adapter_v1` while package execution,
secret resolution, network access, route exposure, and activation remain
false. This replaces the historical C2 assertion that the then-Stripe-only
profile refused Wompi; C3A intentionally superseded that expectation.

The C4B1/C4B2/C4B3/C4B4A classes are source-level pure contracts, not adapter
operations. They do not resolve a public key or secret, open a connection,
return/persist a wire request, or create a transaction. The C4B2 preflight
constructs one exact synthetic body/header/signature only inside a pure call,
then returns domain-separated and double-hashed evidence with every external/
business effect false.
C4B3 accepts already-captured arrays only. It performs no HTTP, cannot verify a
payment, and cannot authorize an order mutation.
C4B4A prepares only hash-bound no-contact evidence. It deliberately reports
that its claim is not persisted, replay protection is inactive, and execution
is not authorized; a later durable core gate must close those facts.

C4C1 constructs the final merchant path only after an exact `pub_test_` value
matches the C4B1 plan hash. Successful containment returns two contract links
and hashes, never the public key, raw tokens, response body, or headers. The
real transport exists but was not contacted in this package gate.

RED-CMS core adopted package `0.1.4` through the completed C4B gates. C4C2 must
now pin `0.1.5` and add the Owner-gated CLI plus disposable no-contact proof.
This package change does not silently update or install any RED-CMS client.

## Run the offline proof

```sh
PHP_CLI=/path/to/php RED_CMS_CORE=/path/to/redcms scripts/test.sh
```

The current focused suite passes 34 C2 provider-contract assertions, 81
package/current-core assertions, 29 C4B1 merchant-contract assertions, 49
C4B2 consent/wire assertions, 48 C4B3 response-containment assertions, 52
C4B4A no-contact-attempt assertions, and 28 C4C1 merchant-read assertions: 321
total. See
[`docs/COLOMBIA-C2-PACKAGE-SKELETON.md`](docs/COLOMBIA-C2-PACKAGE-SKELETON.md)
and
[`docs/COLOMBIA-C2-ACCEPTANCE.md`](docs/COLOMBIA-C2-ACCEPTANCE.md)
and
[`docs/COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md`](docs/COLOMBIA-C4B1-MERCHANT-CONTRACT-PREFLIGHT.md)
and
[`docs/COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md`](docs/COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md)
and
[`docs/COLOMBIA-C4B3-RESPONSE-CONTAINMENT.md`](docs/COLOMBIA-C4B3-RESPONSE-CONTAINMENT.md)
and
[`docs/COLOMBIA-C4B4A-NO-CONTACT-ATTEMPT.md`](docs/COLOMBIA-C4B4A-NO-CONTACT-ATTEMPT.md)
and
[`docs/COLOMBIA-C4C1-MERCHANT-READ-TRANSPORT.md`](docs/COLOMBIA-C4C1-MERCHANT-READ-TRANSPORT.md).
