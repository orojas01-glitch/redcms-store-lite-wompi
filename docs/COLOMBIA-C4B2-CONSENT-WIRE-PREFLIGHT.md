# Colombia C4B2 Consent And Transient Wire Preflight

Status: complete in package version `0.1.2` as a credential-free, no-contact
source contract. No account/dashboard, credential resolution, provider API
request, transaction, database, Store Lite installation, demo, or deployment
state changed.

## Official contract

Current Wompi documentation requires the customer to see and explicitly accept
both current contracts before `acceptance_token` and `accept_personal_auth` are
sent. A Nequi transaction uses private-key Bearer authorization, COP, customer
email, a unique reference, a 10-digit phone, and a server-generated integrity
signature. The signature is SHA-256 over the exact concatenation of reference,
integer amount in cents, currency, and integrity secret.

Sources rechecked 2026-08-23:

- [Tokens de aceptación](https://docs.wompi.co/docs/colombia/tokens-de-aceptacion/)
- [Transacciones](https://docs.wompi.co/docs/colombia/transacciones/)
- [Métodos de pago — Nequi](https://docs.wompi.co/docs/colombia/metodos-de-pago/#nequi)
- [Firma de integridad](https://docs.wompi.co/docs/colombia/widget-checkout-web/#paso-3-genera-una-firma-de-integridad)

## Two-contract presentation and consent evidence

`WompiContractConsentPresentation.php` converts the valid C4B1 projection into
exactly two ordered Wompi contract links and two separately named required
checkbox-control models. It returns no HTML or raw tokens and performs no
browser rendering or consent recording. Its self-fingerprint changes if a
link, order, purpose, control name, or required fact changes.

`WompiContractConsentEvidence.php` accepts only a valid C4B1 contract
presentation and an exact consent submission containing:

- immutable Store Lite order id and guest-subject SHA-256;
- exact contract and both acceptance-token hashes;
- one consent nonce hash;
- separate presented and accepted facts for the end-user policy and personal-
  data authorization; and
- a caller-supplied acceptance epoch no more than 15 minutes old.

The output binds those hashes/facts with a deterministic consent evidence hash
and exact 15-minute expiry. It returns neither contract links nor raw tokens.
Missing presentation, either missing acceptance, changed contract/token hash,
future/expired time, extra fields, changed evidence, and expired reuse fail
closed before wire construction.

## Transient wire/signature preflight

`WompiNequiTransientWireRequestBuilder.php` accepts a valid existing C2
transaction plan, its original hash-only order evidence, valid unexpired C4B2
consent, and one exact synthetic transient value set. It requires:

- raw email/phone hashes to match the immutable order evidence;
- both raw token hashes to match consent and the reconstructed transaction
  plan;
- Sandbox-only `prv_test_` and `test_integrity_` value families; and
- exact COP/Nequi/order/amount/host/path/method agreement.

Inside one pure call it constructs the exact private-key Bearer header, Wompi
integrity signature, transaction body, and Sandbox POST request. It then
returns only ordered field names, consent/contract/token hashes, a domain-
separated integrity-input evidence hash, a second hash of the signature, and
hashes of the authorization header, body, complete request, and final evidence.

The actual integrity signature is not returned: SHA-256 of the raw integrity
input is the signature itself, so C4B2 deliberately uses a domain-separated
evidence hash and separately hashes the signature. The raw request, header,
signature, email, phone, email/phone hashes, tokens, private key, and integrity
secret are not returned or persisted.

## Acceptance evidence

- 49 focused C4B2 assertions pass.
- 29 C4B1 merchant-contract assertions pass unchanged.
- 34 existing C2 transaction/event assertions pass unchanged.
- 70 package/current-core assertions pass with 14 integrity files and ten
  source/package parity checks.
- All ten source files, ten package copies, registrar, and four tests pass
  PHP lint.
- Malformed/mismatched email, phone, token, plan, order, consent, production-
  family values, extra fields, expired consent, and changed evidence fail
  before a reusable request can exist.
- Redaction checks prove all eight raw transient/signature/header values absent
  from returned evidence.
- Source scans find no request globals, environment reads, file reads, secret
  resolution, database, response emission, socket, cURL, or HTTP client.

## Next boundary

Package `0.1.2` exposes no new adapter operation or transport. A separate core
adoption gate must pin its merged commit and rerun exact disposable lifecycle
proofs. Later C4B gates own contained transaction-create/lookup projections,
one-attempt authorization/claim/state, CLI confirmation, transport doubles,
and no-contact rehearsals. C4C remains the first separately owner-authorized
account/credential/read-only provider-contact gate.
