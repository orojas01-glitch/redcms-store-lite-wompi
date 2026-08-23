# RED-CMS Store Lite Wompi Adapter

This repository is the separately distributed Wompi payment adapter candidate
for RED-CMS Store Lite. Version `0.1.0` completes Colombia C2 as an offline,
disabled-by-default package skeleton. Its initial commercial contract is only
one-time Nequi payments in `COP` through the provider-neutral
`out_of_band_confirmation` initiation mode.

The package is not installed, enabled, or connected to RED-CMS runtime. It has
no Wompi account, key value, customer data, network client, executable provider
operation, public webhook ingress, browser flow, payment, Store Lite mutation,
demo activation, or deployment.

## C2 contents

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
- one hashed non-executable request planner;
- one strict synthetic PENDING-response gate that adopts RED-CMS C1 exactly;
- one one-use sealed in-memory transport double;
- one dynamic-property, 25-hour retry-compatible Sandbox event verifier that
  requires signed-event and lookup agreement; and
- one registration-only typed adapter exposing only `contract.probe` while
  every provider operation returns `provider_transport_disabled`.

## Deliberate current core refusal

Generic RED-CMS discovery and contained runtime registration accept the exact
package and all nine integrity files. The current payment-adapter profile is
still intentionally Stripe-specific: it requires two secret settings and
`api.stripe.com`. C2 proves that profile refuses Wompi with only
`outbound_host_invalid` and `setting_contract_invalid`.

This is a required safety result, not a package defect. Colombia C3 must first
generalize the core payment-adapter profile without weakening the existing
Stripe profile, then prove disposable installation, migrations, registrar,
enable/disable, two-client isolation, and exact cleanup. C2 does not claim the
package is installable or enable-ready.

## Run the offline proof

```sh
PHP_CLI=/path/to/php RED_CMS_CORE=/path/to/redcms scripts/test.sh
```

The current focused suite passes 34 provider-contract assertions and 60
package assertions. See
[`docs/COLOMBIA-C2-PACKAGE-SKELETON.md`](docs/COLOMBIA-C2-PACKAGE-SKELETON.md)
and
[`docs/COLOMBIA-C2-ACCEPTANCE.md`](docs/COLOMBIA-C2-ACCEPTANCE.md).
