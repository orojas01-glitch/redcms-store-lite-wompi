# Colombia C4C1 Read-Only Merchant-Contract Transport

Status: complete in external package version `0.1.5` as an implementation and
sealed-double proof. The real transport exists but was not invoked. No Wompi
account/dashboard, key value, DNS, TLS, HTTP request, provider contact,
transaction, payment, order mutation, demo, or deployment effect occurred.

## Exact operation

The only newly supported provider operation is:

```text
merchant.acceptance-contracts.retrieve-sandbox
GET https://sandbox.wompi.co/v1/merchants/{pub_test_key}
```

It accepts the existing C4B1 hash-only request plan plus one transient Sandbox
public key whose SHA-256 must match the plan. Production prefixes and changed
keys fail before transport invocation. No private, integrity, or event secret
is accepted or resolved.

The cURL transport fixes HTTPS, TLS peer/host verification, GET, no redirect,
no proxy, a 5-second connection timeout, 10-second total timeout, and a 64 KiB
response ceiling. It contains no POST/custom method, authorization header,
production host, credential, persistence, delay, or retry path.

## Response containment

HTTP 200 JSON must match the existing strict two-contract response gate. Raw
response bytes and both raw tokens are cleared before the adapter result is
created. The bounded result retains only:

- two ordered Wompi-controlled HTTPS contract permalinks;
- separate token, contract, response, projection, request, and transport
  SHA-256 identities;
- HTTP status and response byte count; and
- explicit false facts for provider mutation, transaction creation, payment,
  event registration, order mutation, and retry.

No response body/header or raw public key/token is returned.

## Acceptance

- 28 C4C1 assertions pass with one sealed no-network transport-double call.
- Production prefix and public-key hash drift refuse before the double.
- Throwing and malformed doubles become one indeterminate attempt with no
  retry.
- All 15 reviewed source files are byte-identical to package copies where
  applicable.
- Generic/current-core package acceptance passes 81 assertions with exact
  `0.1.5` identity and all 19 integrity files.
- The complete external suite passes 34 C2, 81 package/current-core, 29 C4B1,
  49 C4B2, 48 C4B3, 52 C4B4A, and 28 C4C1 assertions: 321 total.

## Next boundary

C4C2 must add the core-owned Owner/database/package/setting/evidence/one-
attempt CLI and a disposable no-contact rehearsal. Only after that is reviewed
may C4C3 use owner-entered client-local Sandbox references and perform exactly
one separately confirmed read-only merchant GET. A Wompi transaction remains
outside C4C and requires a later C4D authorization.
