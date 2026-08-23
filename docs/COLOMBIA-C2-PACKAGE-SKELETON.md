# Colombia C2 Offline Wompi Package Skeleton

Status: complete locally at package version `0.1.0`; publication review is
pending. No external provider or RED-CMS installation effect occurred.

## Fixed scope

C2 creates only a separately distributed package and source-level contracts.
The package remains outside RED-CMS core and Store Lite, and it is not copied
into `demo.red-sphere.com` or any client installation.

The package declares:

- id `redcms.store-lite-wompi` and adapter
  `redcms.store-lite-wompi/checkout`;
- RED-CMS `>=5.1 <6.0`, PHP `>=8.2 <9.0`, and Store Lite
  `>=0.1.35 <1.0`;
- `wompi.public-key` as an unset client-local non-secret text setting;
- `wompi.private-key`, `wompi.integrity-key`, and `wompi.event-secret` as
  distinct secret references without defaults;
- only `sandbox.wompi.co` as a declared future outbound host;
- one POST/server-signature route at
  `/addons/redcms/store-lite-wompi/provider-events`, registered only to a
  non-operational refusal; and
- two unexecuted package migrations with exact SHA-256 inventory.

## Offline contracts

The request planner accepts only hashes for order snapshot, idempotency,
customer email/phone, both current acceptance tokens, and the provider
contracts. It accepts only availability facts for the public setting and three
secret references. Its output fixes Sandbox Wompi, Nequi, COP, POST
`/v1/transactions`, `out_of_band_confirmation`, the immutable order/amount,
request/acceptance hashes, a deterministic plan hash, and every effect false.
It cannot construct a wire body or resolve any secret.

The response gate accepts only an exact synthetic Wompi transaction projection
whose state is `PENDING` and whose reference, amount, currency, and method
match the plan. It emits exactly the merged C1 value: opaque provider
reference, pending state, and `approve_in_provider_app`; no URL is accepted or
returned.

The sealed transport double is one-use, commits to the complete plan hash,
discards its synthetic response after one attempt, returns only the response-
gate projection, and refuses replay or changed plans with retry false. It has
no network primitive.

The event verifier consumes only an already-captured typed Sandbox projection,
a matching typed lookup projection, one synthetic event secret, the current
time, and prior evidence hashes. It resolves the bounded provider-supplied
signed property list in declared order, verifies the event checksum, accepts
the documented 24-hour retry schedule with a one-hour safety margin, refuses
replay, requires exact event/lookup agreement, and projects only APPROVED to
proposed paid or DECLINED/ERROR to proposed failed. It never authorizes or
applies an order mutation.

## Persistence contract

The migrations retain only client/order hashes, opaque provider transaction
reference, integer amount, COP, NEQUI, closed status/outcome, request/
acceptance/event hashes, and bounded timestamps. They deliberately contain no
customer email/phone, acceptance token, key, secret, raw event, provider body,
provider header, redirect URL, checkout URL, or reusable provider payload.

The migrations are inventory only in C2. They are not imported, planned,
installed, or executed. C3 owns fresh disposable-database migration proof.

## Core boundary discovered

Generic manifest discovery validates version `0.1.0`, all nine payload hashes,
the exact dependency/settings/migrations/route/host surfaces, and the contained
registrar. The typed `contract.probe` runs only in an isolated CLI test and
returns fixed false-effect facts. Every provider operation remains unsupported.

The current core payment-adapter profile remains
`store_lite_stripe_checkout_adapter_v1`; it requires two secret settings and
outbound host `api.stripe.com`. It therefore refuses Wompi exactly. C3 must add
a provider-neutral or separately closed Wompi profile while preserving every
existing Stripe assertion and refusal.

## C3 boundary

C3 may modify core only to recognize the exact reviewed Wompi package profile,
then prove a fresh disposable installation and lifecycle. It must not add a
production host, credential value, provider request, payment, public ingress,
browser checkout, hosted-demo change, or client deployment. Wompi Sandbox
contact remains C4 and separately approval-gated.
