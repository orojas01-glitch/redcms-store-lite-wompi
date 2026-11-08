# Wompi W4: transaction lookup transport

Status: offline package gate, version `0.4.0`. The class is shipped but this
package still publishes no route and resolves no secret. RED-CMS core owns the
default-disabled webhook endpoint that uses it.

`RED_CMS_Store_Lite_Wompi_Transaction_Lookup_Transport::get($url)` performs
the confirmation GET that the W3 coordinator asks for:

- exactly `https://sandbox.wompi.co/v1/transactions/{id}`, with an id of 1 to
  64 letters, digits or hyphens; anything else returns `null` before curl is
  touched;
- one HTTPS GET, no redirects, TLS peer and host verification on, 5 s connect
  and 10 s total timeouts, 16 KB of headers and 64 KB of body at most;
- no credential is sent, since the transaction resource is public; and
- any failure, non-200 status or oversized response returns `null`, which the
  coordinator reports as a transient `503` so Wompi retries.

Production (`production.wompi.co`) is deliberately refused until Oscar
authorizes live mode; adding it means widening this URL check and the
manifest `outboundHosts` together.

`tests/w4-transaction-lookup-self-test.php` checks the accepted URL, twelve
refusals that never reach the network, and the transport's source limits.
