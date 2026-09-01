# Bug report: `get_question_list` crashes instead of returning its own intended error when the target category doesn't exist yet

## Summary

Running any Gitsync import/list/export/delete operation against a context whose question
category tree hasn't been created in Moodle yet (typically: a brand new course or module
that has never had its question bank opened) fails with an unhandled PHP warning:

```
Attempt to read property "id" on false
Failed to get list of questions from Moodle.
```

This is misleading and unhelpful. The code already has a specific, well-worded error
message for exactly this situation - it just never gets reached, because of a missing
guard a few lines earlier in the same function.

## Root cause

`classes/external/get_question_list.php`, `execute()`, the loop that walks a
`qcategoryname` path (e.g. `top`, or `top/cat-1/subcat-1`) down through
`question_categories`:

```php
$catnames = split_category_path($params['qcategoryname']);
$parent = 0;
foreach ($catnames as $catname) {
    $category = $DB->get_record(
        'question_categories',
        ['name' => $catname, 'contextid' => $thiscontext->id, 'parent' => $parent],
        'id, parent, name'
    );
    $parent = $category->id;   // <-- crashes here whenever $category is false
}
```

`$DB->get_record()` returns `false` (Moodle's normal `IGNORE_MISSING` behaviour), not an
exception, when nothing matches. If the very first segment of the path (almost always
`top`, since that's the default `qcategoryname`) doesn't exist for this context yet, the
very next line dereferences `false->id` and PHP raises the warning above.

The function already anticipates "category not found" as a real, expected outcome - a few
lines further down there is:

```php
if (!$category) {
    if ($params['qcategoryname'] === 'top') {
        throw new \moodle_exception('categoryerrornew', 'qbank_gitsync', null, $params['qcategoryname']);
    } else {
        throw new \moodle_exception('categoryerror', 'qbank_gitsync', null, $params['qcategoryname']);
    }
}
```

and `lang/en/qbank_gitsync.php` has a specifically-written, actionable message for the
`top`-not-found case:

```php
$string['categoryerrornew'] = 'Problem with question category: {$a}. If the course or
module is new, please open the question bank in Moodle to initialise it and try Gitsync
again.';
```

That message is exactly right for this situation and was clearly written with it in mind -
it just can't ever be reached, because the loop crashes before control gets there.

## Reproduction

```sh
# A course whose question bank has genuinely never been opened:
php -r '
    define("CLI_SCRIPT", true);
    require("config.php");
    global $DB;
    $context = context_course::instance(<a fresh course id>);
    var_dump($DB->count_records("question_categories", ["contextid" => $context->id]));
    // int(0)
'
```
Then run `importrepotomoodle.php` (or any other Gitsync script) targeting that context with
the default `top` category - the crash above occurs on the very first webservice call.

## Fix (implemented, this checkout)

Break out of the loop as soon as a segment isn't found, instead of dereferencing it:

```php
foreach ($catnames as $catname) {
    $category = $DB->get_record(
        'question_categories',
        ['name' => $catname, 'contextid' => $thiscontext->id, 'parent' => $parent],
        'id, parent, name'
    );
    if (!$category) {
        // Stop descending immediately - don't dereference a false result. Leaves
        // $category as false so the existing check below reports it properly
        // (including the more helpful 'categoryerrornew' message when the whole
        // path is just 'top', e.g. a context whose question bank has never been
        // opened in Moodle and so has no category tree yet at all).
        break;
    }
    $parent = $category->id;
}
```

This is minimal and purely corrective: it doesn't change behaviour for any path that
*does* resolve successfully, and for a path that doesn't resolve, it changes an unhandled
PHP warning into the `moodle_exception` the surrounding code was already written to throw.
Confirmed fixed by reproducing against a real course context with no question categories:
before the fix, the raw warning; after, the intended message verbatim:

```
Problem with question category: top. If the course or module is new, please open the
question bank in Moodle to initialise it and try Gitsync again.
```

## Related, separate observation (not a bug, flagging for visibility)

On Moodle 5.2.1, `question_get_top_category()` (Moodle core, `lib/questionlib.php`)
explicitly rejects `CONTEXT_COURSE` (`50`) with *"Invalid context level: 50 ... must be
CONTEXT_MODULE"*. This matches what this project's own README already documents under
"Moodle context": from Moodle 5 onward, question banks live at the module level (a
"question bank" activity inside a course), not the course context directly - a course
context can no longer have its own `top` question category at all, initialised or not.
This isn't something the fix above changes or could change; it means an import/export
targeted at `--contextlevel course` on Moodle 5+ will still fail even after this fix,
correctly, since the target context genuinely cannot hold questions directly on that
version - the fix here only ensures the *reported* error is the accurate, intended one
instead of a crash, for every affected context level, not just Moodle 5 course contexts.
