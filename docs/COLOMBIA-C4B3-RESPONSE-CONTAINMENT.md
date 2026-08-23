# Colombia C4B3 Transaction Response Containment

Status: complete in package version `0.1.3` as a credential-free, no-contact
source contract. No account/dashboard, credential resolution, provider API
request, transaction, database, Store Lite installation, demo, or deployment
state changed.

## Official response contract

Current Wompi documentation describes a successful transaction creation as
HTTP 201 with a `data` object. A newly created transaction is `PENDING` and the
documented fields include its id, reference, amount in cents, currency, payment
method type, status, customer email, merchant/payment-method detail, creation
time, and status message. Transaction lookup uses
`GET /v1/transactions/{transaction_id}` and returns a smaller `data`
projection containing the same transaction identity, reference, amount,
currency, payment-method type, status, and status message.

Initial Nequi containment accepts only `PENDING` for creation and only
`PENDING`, `APPROVED`, `DECLINED`, or `ERROR` for lookup. `VOIDED` is excluded
from this Nequi-only scope.

Sources rechecked 2026-08-23:

- [Transacciones](https://docs.wompi.co/docs/colombia/transacciones/)
- [Métodos de pago — Nequi](https://docs.wompi.co/docs/colombia/metodos-de-pago/#nequi)

## Strict create containment

`WompiTransactionResponseContainment.php` accepts only:

- a valid existing C2 transaction plan;
- valid self-fingerprinted C4B2 wire evidence;
- exact HTTP 201;
- one exact top-level `data` object no larger than 65,536 encoded bytes; and
- required id/reference/amount/COP/NEQUI/PENDING fields that match the plan and
  wire evidence exactly.

Documented optional fields are validated before being discarded. The result
contains only the opaque provider reference, immutable Store Lite reference and
amount, fixed currency/method, PENDING state, existing C1 initiation projection,
wire/consent hashes, sorted names of discarded fields, a safe-projection hash,
and a deterministic evidence hash. It returns no raw response, headers, email,
phone, merchant/payment detail, status message, or raw provider payload.

## Strict lookup containment

Lookup requires exact HTTP 200 and valid untampered create evidence. The id,
reference, amount, currency, and payment method must agree exactly. The four
accepted statuses produce only these proposed outcomes:

- `PENDING` -> non-final `pending`;
- `APPROVED` -> final proposed `paid`;
- `DECLINED` -> final proposed `failed`; and
- `ERROR` -> final proposed `failed`.

These are response observations, not authoritative payment facts. Every valid
and refused result keeps payment verification, signed-event agreement, payment
application, Store Lite mutation authority, provider mutation, and retry
authorization false. A later gate must require independent event agreement and
one-attempt state before any order mutation can become eligible.

## Acceptance evidence

- 48 focused C4B3 assertions pass.
- 49 C4B2, 29 C4B1, and 34 C2 assertions pass unchanged.
- 72 package/current-core assertions pass with 15 integrity files and eleven
  source/package parity checks.
- All eleven source files, eleven package copies, registrar, and five tests
  pass PHP lint.
- HTTP, missing/extra field, reference, amount, currency, method, create-state,
  lookup-state, bounded optional data, and nested discarded-object mismatches
  fail closed.
- Tampered wire, create, projection, and lookup evidence fails validation.
- Redaction checks prove raw email, phone, merchant name, and status message are
  absent from returned create evidence.
- Source scans find no request globals, environment reads, secret resolution,
  database, response emission, socket, cURL, or HTTP client.

## Next boundary

Package `0.1.3` exposes no new adapter operation or transport. A separate core
adoption gate must pin its merged commit and rerun exact disposable lifecycle
proofs. C4B4 owns one-attempt execution authority/claim/state and must remain
no-contact until its pure and disposable proofs pass. C4C remains the first
separately owner-authorized account/credential/read-only provider-contact gate.
