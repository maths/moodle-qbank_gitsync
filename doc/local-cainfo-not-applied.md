# Bug report: `curl.cainfo` is not applied by `curl_exec()`, breaking self-signed/local-dev Moodle instances

## Summary

Running any Gitsync CLI script (`importrepotomoodle.php` and others) against a Moodle
instance with a self-signed TLS certificate fails with:

```
Broken JSON returned from Moodle:

```

(nothing printed after the colon — the response body is empty). This looks like a Moodle
or JSON problem but isn't: it's a TLS handshake failure at the curl layer that the code
never surfaces, plus a second, independent problem that means the standard fix (pointing
`curl.cainfo` at your CA) silently doesn't work on at least some PHP builds.

Two distinct root causes are described below, since both need to be understood to fix a
self-signed-certificate setup completely. A minimal, backward-compatible code fix for the
second one is included and proposed for inclusion upstream.

## Environment where this was observed and reproduced

- Client: PHP 8.5.8 (cli) (Homebrew build, macOS), `curl_version()` reports libcurl
  8.21.0, `ssl_version` OpenSSL/3.6.3.
- Server: Moodle running under `moodlehq/moodle-docker`, Apache serving a locally
  generated self-signed certificate for `CN=localhost` over HTTPS.
- For comparison, the system `curl` CLI on the same machine (`/usr/bin/curl`) uses a
  *different* TLS backend - libcurl 8.7.1 with SecureTransport (Apple's native TLS), not
  OpenSSL - which behaves more leniently and does not exhibit cause 1 below.

## Cause 1: OpenSSL rejects a self-signed leaf certificate as its own trust anchor unless it has `CA:TRUE`

`curl_request::execute()` (`classes/curl_request.php`) is a thin wrapper:

```php
public function execute() {
    return curl_exec($this->curlhandle);
}
```

`curl_exec()` returns `false` on any transport-level failure, including a TLS handshake
failure - it does not throw, and this return value is never checked here. Every call site
that consumes the response does the equivalent of:

```php
$moodlequestionlist = json_decode($response);
if (is_null($moodlequestionlist)) {
    echo "Broken JSON returned from Moodle:\n";
    echo $response . "\n";
    ...
}
```

`json_decode(false)` (PHP coerces `false` to `""` in string context) returns `null`, so a
TLS failure and an actual malformed-JSON response produce the identical, uninformative
"Broken JSON" message with an empty body - there is no way to tell from the output that
the real problem is TLS, let alone what about TLS.

The specific TLS failure: a certificate that is self-signed (`subject` == `issuer`) but
does **not** carry `X509v3 Basic Constraints: critical, CA:TRUE` is rejected by OpenSSL's
chain-builder when presented as its own trust anchor via `CURLOPT_CAINFO`/`curl.cainfo`,
with `SSL certificate problem: self-signed certificate` (OpenSSL error 18,
`X509_V_ERR_DEPTH_ZERO_SELF_SIGNED_CERT`) - **even when that exact certificate file is the
one named in the CA bundle.** This is despite the same certificate being accepted by
Apple's SecureTransport-backed `curl` CLI without complaint, which is what makes this easy
to misdiagnose as "the file path must be wrong" when it works from the command line but
not from PHP.

### Reproduction

```sh
# Certificate WITHOUT CA:TRUE (typical output of a quick `openssl req -x509 ...`
# with no -addext basicConstraints) - fails:
openssl s_server -accept 9443 -cert no-catrue.pem -key no-catrue.key -www &
php -r '
    $ch = curl_init("https://localhost:9443/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CAINFO, "no-catrue.pem");
    var_dump(curl_exec($ch));       // bool(false)
    echo curl_error($ch), "\n";     // SSL certificate problem: self-signed certificate
'

# Same certificate regenerated with -addext "basicConstraints=critical,CA:TRUE" - works:
openssl s_server -accept 9443 -cert with-catrue.pem -key with-catrue.key -www &
php -r '
    $ch = curl_init("https://localhost:9443/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CAINFO, "with-catrue.pem");
    var_dump(curl_exec($ch));       // string(...) - the actual response body
'
```

### Fix

Anyone generating a self-signed cert for local Moodle development should include
`CA:TRUE`, e.g.:

```sh
openssl req -x509 -newkey rsa:2048 -nodes \
  -keyout localhost.key -out localhost.pem \
  -days 3650 -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
  -addext "basicConstraints=critical,CA:TRUE" \
  -addext "keyUsage=critical,digitalSignature,keyEncipherment,keyCertSign"
```

This is a documentation/tooling issue for whatever generates the dev certificate (in our
case `moodle-docker`'s locally-created `assets/ssl/localhost.pem`/`localhost.key`, which
are not part of `moodle-docker` itself), not something Gitsync's code can fix - Gitsync
just needs to actually surface *which* TLS error occurred instead of swallowing it as
"Broken JSON" (see "Suggested follow-up" below).

## Cause 2: `curl.cainfo` (the php.ini directive) is not consulted by `curl_exec()`, at least on this build

This is the one that actually blocked progress even after fixing cause 1, and is the
reason this doc exists separately from just "regenerate your cert."

This project's own guidance (`doc/localsetup.md`'s Windows CA-bundle section, and the
natural reading of `doc/webservicesetup.md`/general PHP practice) is: if curl can't verify
a certificate, set `curl.cainfo` in `php.ini` (or pass `-d curl.cainfo=/path/to/cert.pem`
on the command line) to point at the right CA file. `curl_request.php` never calls
`curl_setopt($handle, CURLOPT_CAINFO, ...)` itself, so it relies entirely on this ini
fallback (PHP's curl extension is documented to apply `curl.cainfo` automatically to a
handle when `CURLOPT_CAINFO` has not been explicitly set on it).

On the environment described above, this fallback does not happen. Confirmed as follows:

```sh
# The ini value genuinely is set correctly for this process:
php -d curl.cainfo=/path/to/with-catrue.pem -r 'var_dump(ini_get("curl.cainfo"));'
# string(24) "/path/to/with-catrue.pem"

# And yet, with a certificate already confirmed (Cause 1's fix applied) to work when
# CURLOPT_CAINFO is set explicitly via curl_setopt():
php -d curl.cainfo=/path/to/with-catrue.pem -r '
    $ch = curl_init("https://localhost:9443/"); // same server, same cert as the working case above
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // deliberately NOT calling curl_setopt CURLOPT_CAINFO here - relying on the ini fallback
    var_dump(curl_exec($ch));       // bool(false)
    echo curl_error($ch), "\n";     // SSL certificate problem: self-signed certificate
'
```

Same certificate, same server, same process-visible ini value - the *only* difference
between success and failure in this pair of tests is whether `CURLOPT_CAINFO` was set
explicitly via `curl_setopt()` versus left to the `curl.cainfo` ini fallback. This means
every piece of guidance in this project (and generally, elsewhere) that says "just set
`curl.cainfo`" silently does not work here, with no error indicating why - the failure
looks identical to Cause 1's, and identical to a genuinely wrong file path.

This was not narrowed down to a specific PHP/libcurl/OpenSSL version combination boundary
- it is reported here as an observed, reproducible fact on the environment above, not
authoritatively diagnosed as a specific upstream PHP or curl bug. It may be
Homebrew-build-specific, OpenSSL-3.x-specific, or something else; a note in this project's
own docs that this fallback is not universally reliable, plus a workaround that does not
depend on it, seems like the more useful response than chasing the exact boundary.

## Proposed fix (implemented, this checkout)

`classes/curl_request.php`'s constructor now explicitly applies `CURLOPT_CAINFO` when a
`GITSYNC_CAINFO` environment variable is set, rather than relying solely on the ini
fallback:

```php
public function __construct($url) {
    $this->curlhandle = curl_init($url);
    $cainfo = getenv('GITSYNC_CAINFO');
    if ($cainfo !== false && $cainfo !== '') {
        curl_setopt($this->curlhandle, CURLOPT_CAINFO, $cainfo);
    }
}
```

This is opt-in and additive: when `GITSYNC_CAINFO` is not set (the case for every existing
user talking to a real Moodle instance with a publicly-trusted certificate), behaviour is
completely unchanged - no new curl option is set, the existing ini-based default handling
applies exactly as before. It only takes effect for someone deliberately setting the
environment variable, which for local/self-signed setups now reliably works regardless of
whether the ini fallback happens to be respected by their particular PHP build:

```sh
GITSYNC_CAINFO=/path/to/localhost.pem php importrepotomoodle.php ...
```

## Suggested follow-up (not implemented here, flagging for consideration)

Independently of both causes above, the "Broken JSON returned from Moodle" message
(repeated at ~15 call sites across `cli_helper.php`, `import_repo.php`, `export_repo.php`,
`export_quiz.php`, `import_quiz.php`, `tidy_trait.php`, `export_trait.php`) could be made
far more useful by checking `curl_errno()`/`curl_error()` on the handle whenever
`curl_exec()` returns `false`, and reporting that specifically instead of (or alongside)
the generic message. As it stands, a TLS failure, a DNS failure, a connection refusal, and
an actually-malformed JSON response from Moodle are all indistinguishable to the user. This
would have surfaced both causes in this report immediately, rather than requiring a manual
patch to `curl_request.php` to get a real error message out.
