# Bug report: `ob_clean()` crashes the question-import webservice when `output_buffering` is off

## Summary

Importing a question (or, as first observed, a `gitsync_category.xml` category file) can
fail with the webservice returning:

```
ob_clean(): Failed to delete buffer. No buffer to delete
{path} not imported.
Stopping before trying to import questions.
```

This looks like a problem with the file being imported, but isn't - it's a PHP output
buffering configuration mismatch inside the plugin's own webservice handler, unrelated to
the file's content or the target category.

## Root cause

`classes/external/import_question.php`, `execute()`, right before returning its response:

```php
ob_clean();
return $response;
```

This assumes an output buffer is already active at this point in every environment. It
isn't always. `ob_clean()` raises a PHP warning - `Failed to delete buffer. No buffer to
delete` - when called with no active buffer (`ob_get_level() === 0`), and Moodle's
webservice error handling converts that warning into the exception this shows up as
client-side.

Confirmed on the affected server: `php -i | grep output_buffering` reports
`output_buffering => 0 => 0` (Off). With that setting, PHP does not implicitly open a
buffer at request start, and nothing earlier in this call path (`qformat_xml::
importprocess()`/`importpreprocess()`/`importpostprocess()`, all core Moodle question
import code, not this plugin's own) opens one either - so by the time execution reaches
this `ob_clean()`, there is genuinely nothing to clean.

This is presumably harmless (or simply never triggered) wherever `output_buffering` is
non-zero, which is a common default in many PHP distributions - which would explain why it
wasn't caught before.

## Reproduction

```sh
php -d output_buffering=0 -r 'ob_clean();'
# PHP Warning:  ob_clean(): Failed to delete buffer. No buffer to delete in ...

php -d output_buffering=4096 -r 'ob_clean();'
# (no warning - a buffer is active)
```

Then, with `output_buffering=0` on the Moodle server (as observed above): import any
question or category file via `importrepotomoodle.php` into a context - the import call
fails with the message shown in the Summary, regardless of the file being imported.

## Fix (implemented, this checkout)

Guard the call instead of assuming a buffer is always open:

```php
if (ob_get_level() > 0) {
    ob_clean();
}
return $response;
```

When a buffer *is* active (whatever environment/configuration originally motivated adding
this call), behaviour is unchanged - it's still cleaned exactly as before. When none is
active, the call is simply skipped, which is correct: there is nothing to discard either
way, since nothing was buffered.

Confirmed fixed by re-running an import that previously failed with this exact error
against the same server (`output_buffering=0` unchanged) - now completes successfully
("Added 36 questions.").

## Note on how this was found

This surfaced while testing a *targeted subdirectory* import
(`--subdirectory top/highlight/html-classic`) into a freshly-created Moodle 5 question
bank module with no existing categories - i.e. every category file in the target's
ancestor chain also needed importing on the same run as the questions. It's not specific
to that scenario; any question or category import through this webservice function will
hit the same unconditional `ob_clean()` on an environment with `output_buffering=0`. The
category-file angle just happened to be how it was first exercised, since a plain
whole-repository import (no `--subdirectory`) that already has an established manifest
would only import *changed* questions on a re-run and might not exercise this path every
time.
