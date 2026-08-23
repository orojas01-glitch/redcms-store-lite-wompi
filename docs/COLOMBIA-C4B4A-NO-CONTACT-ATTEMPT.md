# Colombia C4B4A Pure No-Contact Attempt Contract

Status: complete in package version `0.1.4` as a credential-free, no-contact
source contract. No account/dashboard, credential resolution, provider API
request, transaction, database, Store Lite installation, demo, or deployment
state changed.

## Narrow purpose

`WompiNoContactAttemptContract.php` adds three pure projections around the
already-reviewed C2, C4B2, and C4B3 evidence:

1. authorize one exact sealed-double-only, no-contact attempt;
2. prepare the first and only claim evidence; and
3. project immutable claim/pending/approved/failed observations from already-
   contained create and lookup evidence.

This is C4B4A, not durable execution. It contains no database, persistence,
transport, secret resolver, network client, provider request, or order writer.

## Authorization contract

Authorization requires valid self-fingerprinted C2 plan and C4B2 wire evidence
with exact order/amount agreement. Its exact hash-only scope requires separate
client, database, actor, secret-availability, and nonce identities; fresh Owner
and order authority; enabled package and Store Lite facts; one-attempt/no-retry
confirmation; and explicit network/provider/order denial. The issue/expiry
window is at most 15 minutes.

The result fixes operation `checkout.create-sandbox-no-contact` and transport
`sealed_double_only`. It binds the plan, request, wire, consent, scope, actor,
database, and time identities in one deterministic authorization hash. It
authorizes no provider contact/mutation or Store Lite mutation.

## Claim-preparation honesty

Claim preparation requires the exact valid authorization, a distinct claim
nonce, attempt number one, an empty prior-claim evidence list, current time,
and explicit one-attempt/no-retry/durable-claim confirmations. It consumes the
modeled allowance by setting remaining attempts to zero.

The pure package does not claim durability it does not possess. Every valid
claim requires these facts:

- `durableClaimRequired=true`;
- `claimPersisted=false`;
- `replayProtectionActive=false`; and
- `executionAuthorized=false`.

A later core-owned C4B4B gate must atomically persist and audit the authorization
and claim before any transport double or operator command can exist.

## Observed-state projection

The claim-only state is `claim_prepared`. Exact C4B3 create evidence advances
only to `pending_observed`. A matching contained lookup may project
`pending_observed`, `approved_observed`, or `failed_observed`. APPROVED remains
only proposed paid evidence; payment verification, signed-event agreement,
payment application, provider/order mutation, and retry stay false.

Lookup without its create evidence, mismatched order/amount/provider identity,
tampered hashes, changed semantics, a second attempt, prior claim evidence,
expired authority, missing revalidation, or widened effect confirmation fails
closed.

## Acceptance evidence

- 52 focused C4B4A assertions pass.
- 48 C4B3, 49 C4B2, 29 C4B1, and 34 C2 assertions pass unchanged.
- 74 package/current-core assertions pass with 16 integrity files and twelve
  source/package parity checks.
- All twelve source files, twelve package copies, registrar, and six tests pass
  PHP lint.
- Source scans find no request globals, environment reads, secret resolution,
  database, response emission, socket, cURL, or HTTP client.

## Next boundary

Package `0.1.4` exposes no new adapter operation or transport. A separate core
adoption gate must pin its merged commit and rerun exact disposable lifecycle
proofs. C4B4B must supply atomic durable authorization/claim and real replay
protection while remaining no-contact. Later gates separately own the sealed
transport-double runner, dry-run-first CLI, and disposable no-contact rehearsal.
C4C remains the first separately owner-authorized account/credential/read-only
provider-contact gate.
