# test262 data

All files under this directory are **exact, unmodified copies** from the
[TC39 test262 repository](https://github.com/tc39/test262), mirrored at the
same relative path under `test/built-ins/Temporal/`.

## Rules

- **No custom test files.** Every `.js` file here must be a verbatim copy of
  the corresponding file in `tc39/test262`. Do not write, modify, simplify, or
  approximate test262 content.
- **No extra files.** If a file does not exist in the upstream repo, it must
  not exist here.
- **Directory structure must match** the upstream layout exactly
  (`PlainDate/compare/`, `PlainDate/from/`, `PlainDate/prototype/…`, etc.).
- **Do not edit file contents** for any reason — not to simplify, not to fix
  assumptions, not to match our implementation. If a test is not passing, fix
  the implementation, not the test.

## How to add files

1. Find the file in `https://github.com/tc39/test262/tree/main/test/built-ins/Temporal/`
2. Copy the raw content verbatim (including copyright header and `/*--- ---*/` frontmatter).
3. Place it at the matching relative path here.
4. Run `composer test262:build` to regenerate the PHP scripts.
