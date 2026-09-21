# Fuzz Testing

This directory holds Shopware's **property-based tests** ("fuzz tests"). This document explains
what that means, why it exists, and how to run it — no prior fuzz-testing experience assumed.

## What this is

A normal unit test picks a handful of example inputs and checks the output for each one. A
property-based test instead states a **rule that should always be true** ("an invariant") and
then generates a large number of random inputs — around 100 per test by default — to try to
break that rule.

For example, instead of writing:

```php
$filter = new RangeFilter('price', ['gte' => 5]);
static::assertSame(5, $filter->getParameter('gte'));
```

a property-based test says: *"for any key/value pair, `RangeFilter` either accepts it unchanged
or throws — it never silently accepts something invalid."* The test framework then generates
~100 different key/value combinations per run — valid ones, invalid ones, edge cases like empty
strings and arrays — and checks the rule holds for every one of them.

This is not a replacement for regular unit tests. It's a complement, specifically good at
finding edge cases nobody thought to write down by hand.

## Why Shopware has this

This isn't a theoretical exercise. Building the tests currently in this directory surfaced two
real, previously-unknown bugs within minutes each, in code that already had dedicated unit
tests:

1. **`QueryStringParser`** (DAL filter parsing): its `equalsAny` filter encodes multiple values
   by joining them with `"|"` and splitting them back apart later, with no escaping. A value
   containing a literal `"|"` gets corrupted into two separate values, and a value equal to the
   literal string `"0"` gets silently dropped (PHP's `array_filter()` treats `"0"` as falsy).
2. **`FieldSerializer`** (ImportExport): the exact same problem, but on untrusted import data.
   A single import value like `"Sale|Clearance"` — meant as one name — gets silently split into
   two unrelated associations instead of being rejected, even though the code visibly *intends*
   to reject it (there's a dead `str_contains($id, '|')` guard that can never fire, because the
   value has already been split before that guard runs).

Neither bug was caught by the existing example-based tests, because nobody happened to write an
example containing a `|` character. That's exactly the gap this technique is good at closing.

## What makes a good fuzz test target

Not every function benefits equally. Based on the four components covered so far, look for
code that has **most** of these traits:

- **A rule you can state, not just a value you can look up.** You need to be able to say
  "for any input, X is always true" (never throws the wrong thing, output is always valid,
  encoding then decoding always gives the same thing back, doing it twice gives the same
  result as doing it once). If the only way to know the right answer is to compute it by hand
  for each input, there's no invariant to check and fuzzing adds little over a couple of
  well-chosen examples.
- **Parses, serializes, validates, or sanitizes something.** Anything that converts between
  representations (`fromArray`/`toArray`, encode/decode, string ↔ object) or decides
  accept-vs-reject is a natural fit — round-trip and validation invariants fall out of the
  job the code already does. All four tests in this directory are one of these three shapes.
- **Hand-rolled string building with `implode`/`explode`/concatenation**, instead of a real
  format like JSON. This is the single strongest warning sign in this codebase so far — both
  real bugs found here were exactly this shape: a delimiter-based encoding with no escaping.
  Grep for a class that both `implode`s and `explode`s the same character and you've likely
  found a candidate.
- **A security or trust boundary.** Sanitizers, input validators, signature/permission checks —
  anywhere the code's whole job is "never let a dangerous thing through" benefits from being
  thrown a wide variety of adversarial input, not just the couple of attack strings someone
  remembered to hand-write.
- **Floating-point or rounding math.** Money/price/tax calculations are a classic source of
  bugs around rounding direction, negative numbers, and precision that are easy to miss by
  hand and easy to generate.
- **Fast and side-effect free to construct.** Ideally `new ClassName(...)` directly, no live
  database or kernel required (every test in this directory works this way) — a fuzz test runs
  its input through the code ~100 times per run, so anything slow to set up makes the whole
  suite slow.

Poor fits — don't force it:

- Code that needs a live database, network call, or full kernel boot just to construct.
  Possible in principle, but slow, and this suite is meant to stay fast enough to run on every
  local iteration.
- Code with side effects that are hard to observe from outside (sends an email, writes a file)
  unless you're specifically testing that the side effect always/never happens.
- Code whose behavior depends on the current time or on external randomness, unless you can
  control/inject it — otherwise a "failure" may not be reproducible even with the seed.
- Simple, low-branching code (a getter, a one-line wrapper) — a single example test already
  covers it as well as a fuzz test would.

## How it works

The tests use [`giorgiosironi/eris`](https://github.com/giorgiosironi/eris), a PHP
property-based testing library, via a plain PHPUnit trait — these are ordinary PHPUnit test
methods, run with the ordinary PHPUnit runner, nothing exotic to install or learn beyond PHPUnit
itself.

A test looks like this:

```php
public function testConstructionNeverSilentlyAcceptsInvalidInput(): void
{
    $this->forAll(
        Generators::elements('lte', 'lt', 'gte', 'gt', 'foo', ''),   // which keys to try
        Generators::oneOf(Generators::string(), Generators::bool()) // which values to try
    )->then(function (string $key, mixed $value): void {
        // this closure runs ~100 times with different generated ($key, $value) pairs;
        // ordinary static::assert*() calls inside it
    });
}
```

Two things make this practical rather than just noisy:

- **Shrinking.** When a generated input fails, Eris doesn't report the huge random string it
  happened to find first — it automatically searches for the smallest, simplest input that
  still reproduces the failure. The `FieldSerializer` bug above was found from a random string
  dozens of characters long; Eris shrank it down to the minimal repro `'n|n'` on its own.
- **Reproducibility.** A failing test prints a line like:
  ```
  ERIS_SEED=1789997945457331 vendor/bin/phpunit --filter '...'
  ```
  Running that exact command replays the exact same generated inputs, so a red run is
  debuggable, not a one-off you can't get back.

## What's covered today

| Test | Component | What it checks |
|---|---|---|
| `RangeFilterFuzzTest` | `RangeFilter` (DAL search filter) | Invalid input is always rejected, never silently accepted |
| `QueryStringParserFuzzTest` | `QueryStringParser` (DAL filter parsing) | Serializing a parsed filter back to an array and re-parsing it never loses information |
| `HtmlSanitizerFuzzTest` | `HtmlSanitizer` | Sanitized HTML never contains a live `<script>` element, event-handler attribute, or `javascript:` URI, even against a corpus of known XSS attack shapes |
| `FieldSerializerFuzzTest` | `FieldSerializer` (ImportExport) | A single association value round-trips correctly; a value containing the `"|"` delimiter is rejected rather than silently corrupted |

`FieldSerializerFuzzTest` currently has one **failing test on purpose** —
`testRejectsASingleIdentifierContainingThePipeDelimiter` documents the bug described above. It
was left red rather than adjusted to pass, so it stays visible as a tracked, reproducible
finding until someone decides how `FieldSerializer` should actually behave.

## Running the tests

From the repository root:

```bash
# the whole fuzz suite
vendor/bin/phpunit --testsuite fuzz

# one component
vendor/bin/phpunit tests/fuzz/Core/Framework/DataAbstractionLayer/Search/Filter/RangeFilterFuzzTest.php

# by test name
vendor/bin/phpunit --filter RangeFilterFuzzTest
```

If you're using the `php-tooling` MCP tool, use `phpunit_run` with `testsuite: fuzz` instead —
it handles environment detection (native/Docker/etc.) for you.

## When a test fails

1. Read the failure message — it includes the exact generated input that broke the rule (and,
   thanks to shrinking, it's usually a short, readable one).
2. Copy the `ERIS_SEED=...` command from the output and re-run it locally to confirm you can
   reproduce it.
3. Work out whether this is:
   - **A real bug in the code under test.** File it / fix it like any other bug — the failing
     test now serves as your regression test once it's fixed.
   - **A false assumption in the test itself.** Property-based tests can also be wrong — e.g.
     an assertion that's too strict, or a generator that produces input the code was never
     meant to handle. Fix the test, not the production code, in that case. (This happened once
     already: an early version of `HtmlSanitizerFuzzTest` used a plain regex check that flagged
     harmless leftover text as if it were a live attribute. The fix was to actually parse the
     output and check real attribute nodes, not to weaken the check.)

Don't narrow a generator just to make a real finding disappear — if you do need to exclude an
input shape because it's a known, separate, out-of-scope issue (like the `QueryStringParser`
cases above), leave a comment explaining why, so the exclusion is visible rather than silent.

## Current status and limitations

- These tests are **not yet wired into CI** — they run locally only, via the commands above.
- Coverage is intentionally small right now: four components, chosen because they were
  high-value (parsing/encoding logic, security-sensitive sanitization) or already suspected of
  having bugs. It's not a blanket policy that every component needs one.
- New tests are written the same way as the ones in this directory — read an existing file
  (`RangeFilterFuzzTest.php` is the simplest starting point) as a template.
