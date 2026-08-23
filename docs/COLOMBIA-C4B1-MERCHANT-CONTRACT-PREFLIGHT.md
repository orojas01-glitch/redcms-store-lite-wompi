# Colombia C4B1 Merchant-Contract Preflight

Status: complete in package version `0.1.1` as a credential-free,
no-contact source contract. No Wompi account, dashboard, credential, API
request, transaction, database, Store Lite installation, demo, or deployment
state changed.

## Official response contract

The current Wompi acceptance-token guide documents public-key retrieval at
`GET /merchants/:llave_publica_de_comercio`. Its response contains exactly the
two relevant objects:

- `presigned_acceptance`, with `acceptance_token`, `permalink`, and type
  `END_USER_POLICY`; and
- `presigned_personal_data_auth`, with `acceptance_token`, `permalink`, and
  type `PERSONAL_DATA_AUTH`.

The guide requires both current contract links to be shown and each contract
to be explicitly accepted before the respective tokens are included in a
transaction request. Source:
[Wompi tokens de aceptación](https://docs.wompi.co/docs/colombia/tokens-de-aceptacion/),
rechecked 2026-08-23.

## Pure request plan

`WompiMerchantContractRequestPlanner.php` accepts only:

- `publicKeySettingPresent: true`; and
- one lowercase SHA-256 of the client-local public-key setting.

Its output fixes provider Wompi, Sandbox environment, host
`sandbox.wompi.co`, method `GET`, path template
`/v1/merchants/{public_key}`, and a 65,536-byte future response ceiling. It
does not receive the public key or construct the final path. Every wire,
secret, network, provider, payment, browser, order, and retry effect is false.

## Contained synthetic response

`WompiMerchantContractResponseGate.php` accepts only the exact two-object
provider projection above. It requires different bounded opaque tokens,
different canonical HTTPS links on Wompi-controlled `.co` or `.com` hosts
without credentials, query strings, or fragments, and exact provider types in
the declared order.

It returns:

- the two ordered contract purposes/types/HTTPS permalinks;
- separate SHA-256 values for the two acceptance tokens;
- contract, response-evidence, and self-fingerprint hashes;
- `userConsentRequired: true`; and
- false facts for presentation, token return, wire-response persistence,
  network/provider effects, payment, browser navigation, order mutation, and
  retry.

The raw tokens are used only to calculate hashes during the pure call and are
absent from the returned projection. The class does not retain them.

## Acceptance evidence

- 29 focused C4B1 assertions pass.
- 34 existing C2 offline transaction/event assertions pass unchanged.
- 64 package/current-core assertions pass, replacing the historical C2
  Stripe-only refusal with exact current Wompi-profile acceptance.
- All seven source files, seven package copies, package registrar, and three
  tests pass PHP lint.
- Eleven declared package payload hashes and all seven source/package parity
  checks pass.
- Source scans find no request globals, environment reads, file reads,
  databases, sockets, cURL, HTTP client, or credential-shaped Wompi value.

## Core adoption boundary

The current core profile accepts this unchanged manifest surface as
`store_lite_wompi_adapter_v1`, which the 64-assertion package suite proves.
Core adoption completed at RED-CMS commit `fcea7d3`, pinning package `0.1.1` at
`7e4f8cb` and rerunning its exact focused/disposable proofs. Package `0.1.2`
remains a separate later adoption and does not silently update a client.

## Next boundary

C4B1 exposes no typed adapter operation or transport. C4B2 is recorded in
[`COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md`](COLOMBIA-C4B2-CONSENT-WIRE-PREFLIGHT.md)
and retains the same provider-contact boundary.
