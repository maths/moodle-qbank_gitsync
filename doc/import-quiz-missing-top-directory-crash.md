# Bug report: importing a quiz whose questions are all `nonquizfilepath` (external) crashes with an uncaught `RecursiveDirectoryIterator` exception

## Summary

`importquiztomoodle.php` crashes instead of importing when the quiz repo directory
(the one passed via `--directory`/`-d`) has no `top/` subdirectory of its own:

```
PHP Fatal error:  Uncaught UnexpectedValueException: RecursiveDirectoryIterator::__construct(...top): Failed to open directory: No such file or directory in .../classes/import_repo.php:...
```

This happens for any quiz whose questions are referenced entirely via `nonquizfilepath`
(i.e. all its questions already live in a separate, already-imported question repo, and
the quiz JSON links to them by manifest path - see `importquiztomoodle.md`'s second
example) rather than via `quizfilepath` (questions living locally alongside the quiz JSON
in that quiz repo's own `top/` tree). A quiz repo of the first kind legitimately has no
`top/` directory - there's nothing local to import - but the crash happens regardless.

## Root cause

`importquiztomoodle.php`'s `import_all()` (`classes/import_quiz.php`) always runs three
phases, in order: `import_quiz_data()` (creates the quiz shell), `call_import_repo()`
(a subprocess call into `importrepotomoodle.php` against the quiz's own `--directory`, to
pick up any local `quizfilepath` questions), then `call_import_quiz_data()` (attaches the
resolved questions - both local and `nonquizfilepath` - to `quiz_slots`).

`call_import_repo()` runs unconditionally, even when the quiz has no local questions at
all. It ends up inside `classes/import_repo.php`, which has two places that construct a
`RecursiveDirectoryIterator` directly on `{directory}/top` (or `{directory}/{subdirectory}`)
with no existence check first:

- `import_categories()` - builds category files from the repo tree.
- `import_questions()` - walks the repo tree for question files to import.

`RecursiveDirectoryIterator::__construct()` throws `UnexpectedValueException` immediately
if the path doesn't exist (unlike, say, returning an iterator that's simply empty), and
neither call site is wrapped in a check or a try/catch, so the whole script dies.

## Reproduction

1. Create a quiz-data JSON whose `questions` array uses only `nonquizfilepath` entries
   (no `quizfilepath` entries), pointing at questions from an already-imported, separate
   repo/manifest.
2. Put that JSON in its own directory with **no `top/` subdirectory** (there's nothing
   local to put there).
3. Run:
   ```sh
   php importquiztomoodle.php -r <root> -d <quiz-directory> -a <quiz-data-file> \
       -p <path-to-other-repo's-manifest> -n <course-id>
   ```
4. The script prints `Quiz imported.` (from the first, quiz-shell-creation phase - see the
   "Note" below on why this message shouldn't be trusted on its own), then crashes with the
   `RecursiveDirectoryIterator` exception above before it ever links the questions - leaving
   an empty quiz behind (`quiz_slots` count 0) despite the misleading first success message.

Both call sites crash the same way, in sequence, once the first is fixed: `import_categories()`
is reached first (during category-file processing) and crashes there; with that fixed,
`import_questions()` is reached next and crashes there instead, on the same missing
directory.

## Fix (implemented, this checkout)

In both places, only build a real `RecursiveDirectoryIterator` when the directory actually
exists; otherwise substitute an iterator that's simply empty, so the existing code below
(which iterates over it, and in `import_categories()`'s case also `rewind()`s and iterates
a second time) runs unchanged and just does zero work - exactly as it would for an existing
but genuinely empty directory.

`classes/import_repo.php`, `import_categories()`:
```php
$this->repoiterator = new \RecursiveIteratorIterator(
    is_dir($subdirectory) ? new \RecursiveDirectoryIterator($subdirectory) : new \RecursiveArrayIterator([]),
    \RecursiveIteratorIterator::SELF_FIRST
);
```

`classes/import_repo.php`, `import_questions()`:
```php
$this->subdirectoryiterator = new \RecursiveIteratorIterator(
    is_dir($subdirectory)
        ? new \RecursiveDirectoryIterator($subdirectory, \RecursiveDirectoryIterator::SKIP_DOTS)
        : new \RecursiveArrayIterator([]),
    \RecursiveIteratorIterator::SELF_FIRST
);
```

(The `SKIP_DOTS` flag is preserved for `import_questions()`'s real-directory branch, since
the original code already used it there; `import_categories()` never used that flag, so its
fix doesn't add it either.)

Confirmed fixed by re-running an import of a quiz with only `nonquizfilepath` questions and
no local `top/` directory against the same server: the script completes with no crash, and
the quiz's `quiz_slots` count in the database matches the number of questions listed in the
quiz JSON exactly (verified directly against the database rather than trusting the script's
own printed output - see the note below).

## Note: the "Quiz imported." message is printed before questions are linked

Independent of this bug, `import_quiz_data()` (the first of the three `import_all()`
phases) prints `Quiz imported.` as soon as the empty quiz shell is created - before
`call_import_repo()` or `call_import_quiz_data()` run. On an unpatched checkout, this
means the script prints an apparently-successful `Quiz imported.` immediately before
crashing on the bug described above, which can be misread as a successful, if oddly
truncated, run. Anyone verifying a quiz import (with or without this fix) should check
`quiz_slots` for the quiz in Moodle directly, rather than relying on this message alone,
since a second `Quiz imported.` is also printed later (from `call_import_quiz_data()`) once
questions are actually linked - the same message is used for both phases.
