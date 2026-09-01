# Bug report: uncaught `TypeError` crashes the whole import when a single file upload's cURL request fails

## Summary

Partway through a large `importrepotomoodle.php` run (importing several hundred question
files), the script can crash entirely with:

```
PHP Fatal error:  Uncaught TypeError: property_exists(): Argument #1 ($object_or_class) must
be of type object|string, null given in classes/import_repo.php:714
```

This aborts the *entire* import - not just the one file that had a problem - discarding
progress on every file after the one that failed (though already-imported files are safely
recorded in the manifest and are picked up again on retry, as documented under "On failure"
in `importrepotomoodle.md`).

## Root cause

`classes/import_repo.php`, `upload_file()`:

```php
$fileinfo = json_decode($this->uploadcurlrequest->execute());
// We're expecting an array containing one file information object.
// If things go wrong, we should get just an error object.
if (!is_array($fileinfo)) {
    if (property_exists($fileinfo, 'error')) {
        ...
```

`$this->uploadcurlrequest->execute()` wraps a plain `curl_exec()` call (see
`classes/curl_request.php`), which returns `false` - not a JSON error body - when the cURL
request fails outright (connection reset, timeout, etc.), rather than completing with an
HTTP error response. `json_decode(false)` decodes the boolean as the empty string and returns
`null`. `null` is not an array, so execution reaches the `if` block, but `property_exists()`
requires its first argument to be an object or a string - passing `null` raises a `TypeError`,
which is uncaught.

This was found in practice with the real failure being a socket read timeout mid-upload:

```
PHP Notice:  curl_exec(): Read of 8192 bytes failed with errno=60 Operation timed out in
classes/curl_request.php on line 82
```

which is exactly the kind of transient failure the surrounding code already appears to intend
to handle gracefully (there's already a branch for a JSON-encoded `error` object from Moodle
itself) - it just doesn't account for cURL failing before Moodle ever gets to respond.

## Reproduction

```sh
php -r '
$x = json_decode(curl_exec_failure_stub());
var_dump($x); // NULL
property_exists($x, "error"); // TypeError: Argument #1 ($object_or_class) must be of type object|string, null given
'
```

In practice: run `importrepotomoodle.php` against a large enough repo, under conditions where
a request to the target Moodle instance occasionally times out or the connection drops mid-
request (observed here on a ~600-question import against a local Docker-hosted instance under
sustained load) - whichever file's upload request fails that way crashes the whole run instead
of being skipped and reported.

## Fix (implemented, this checkout)

Guard against a non-object (`null`) response before calling `property_exists()`, and report a
clear message instead of an uncaught crash:

```php
if (!is_array($fileinfo)) {
    if (is_object($fileinfo) && property_exists($fileinfo, 'error')) {
        echo "{$fileinfo->error}\n";
        echo "Check that the webservice allows file uploads at ";
        echo "Site administration->Server->Web services->External services->";
        echo "qbank_gitsync->Edit->Show more->Can upload files.\n";
    } else if ($fileinfo === null) {
        echo "No response from Moodle - the request may have failed or timed out.\n";
    }
    echo "{$repoitem->getPathname()} not imported.\n";
    return false;
}
```

`upload_file()`'s caller in `import_questions()` already correctly checks the boolean return
value and `continue`s to the next file on failure - that call site needed no change, it was
only ever reachable with a working `upload_file()`. `upload_file()`'s *other* caller, in
`import_categories()`, did **not** check the return value at all (`$this->upload_file(...);`
with the result discarded) - so a failed category-file upload would previously fall through to
using stale `$this->postsettings` file-info from a prior successful call (or none at all, on
the very first file) and produce a confusing downstream failure rather than a clear one. Fixed
alongside the crash itself, for consistency with `import_questions()`'s already-correct
handling:

```php
if (!$this->upload_file($tempcatfile)) {
    echo "Category {$repoitem} not imported.\n";
    continue;
}
```

(and the equivalent for the non-`$newcategory` branch's `$this->upload_file($repoitem);` call.)

Confirmed fixed by re-running the same large import that previously crashed on this exact
error: the run now completes (skipping and reporting any individual file that still fails,
rather than aborting entirely), and a subsequent retry - gitsync's own documented recovery
path for a partially-completed run - picks up cleanly and finishes importing every remaining
file with no further crashes.

## Note: this is a symptom, not evidence of a slow webservice

No `CURLOPT_TIMEOUT`/`CURLOPT_CONNECTTIMEOUT` is set anywhere in `curl_request.php` or its
callers, so the timeout observed here (`errno=60`, `Operation timed out`) is coming from the
underlying OS socket layer, not a configured cURL timeout being hit too aggressively. In the
environment this was found in, the same run also saw unrelated local-filesystem read timeouts
(`git add` failing with "read error while indexing ... Operation timed out" on a OneDrive-
synced working directory) around the same time, suggesting the host was under general I/O
contention during a long, high-volume import rather than gitsync or Moodle being slow. Either
way, a network hiccup mid-import is realistic for *any* environment on a big enough import, and
the interesting bug is that it crashed the whole process instead of being reported and skipped.
