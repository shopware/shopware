---
name: shopware-fuzz-tests
description: Write or evaluate Shopware property-based/fuzz tests. Use when adding a new test under tests/fuzz, deciding whether a function or class is a good fuzz-testing candidate, designing Eris generators or invariants, or investigating a fuzz test failure or its ERIS_SEED reproduction.
license: MIT
---

# Shopware Fuzz Tests

Property-based ("fuzz") tests live under `tests/fuzz/`, namespace `Shopware\Tests\Fuzz\`. Name
the class and file the same as the production class they exercise (`RangeFilterTest`, not
`RangeFilterFuzzTest`) — the `Shopware\Tests\Fuzz\` namespace is what distinguishes it from the
`tests/unit` test of the same name, not a suffix. They run in the `fuzz` PHPUnit testsuite
(`vendor/bin/phpunit --testsuite fuzz`), using
[`giorgiosironi/eris`](https://github.com/giorgiosironi/eris) via `tests/fuzz/FuzzTestCase.php`
— these are plain PHPUnit test methods, not a separate runner.

Read `tests/fuzz/README.md` first — it explains what property-based testing is, why Shopware
has it (with the two real bugs it already found), what makes a good vs. poor fuzz target, and
how shrinking/`ERIS_SEED` reproduction work. This file is the authoring checklist that builds
on it; it does not repeat that background.

General PHPUnit conventions (test shape, `@internal`, data providers, etc.) come from
`shopware-phpunit-tests` and still apply. This skill only covers what's different for a
property-based test.

## Before writing one

Check the target against `tests/fuzz/README.md`'s "What makes a good fuzz test target"
section. In short: it needs a statable invariant (not just a value you'd have to compute by
hand to check), and ideally is a parser/serializer/validator/sanitizer, hand-rolled
string-delimited encoding (`implode`/`explode` — the strongest signal so far), a security
boundary, or rounding/float math. It should construct fast, without a live database or kernel
(`new ClassName(...)` directly).

## Required shape

- `namespace Shopware\Tests\Fuzz\...\`, file under `tests/fuzz/...`, class name `<Thing>Test` —
  the same name the `tests/unit` test for that class would use, no `Fuzz` suffix.
- `extends \Shopware\Tests\Fuzz\FuzzTestCase` (not `TestCase` directly, and don't `use
  Eris\TestTrait;` yourself). `FuzzTestCase` is the one place that trait is used, so it doesn't
  get repeated across every fuzz test class — Shopware is moving away from trait usage where a
  base class works just as well.
- `#[Package('...')]` and `@internal`, same as any other test class.
- **Do not add `#[CoversClass]` (or `#[CoversTrait]`/`#[CoversFunction]`/`#[CoversNothing]`).**
  That attribute is PHPUnit's mechanism for attributing *code coverage* to a class, and
  coverage tracking is `unit`'s job (see `.github/actions/phpunit-run/action.yaml` and
  `php.yml`'s Codecov upload, flagged `phpunit-<suite>`) — fuzz tests are not meant to be a
  second, overlapping coverage source for classes `unit` already covers. `Shopware\Tests\Fuzz\`
  is deliberately **not** registered in `allowedUnitTestClassNamespaces`
  (`src/Core/DevOps/StaticAnalyze/PHPStan/common.neon`), so `CoversAttributeRule` does not
  require — and would in fact reject — a covers attribute here. Don't re-add that registration.
- State the invariant being checked in a docblock on the test method (or the class, if there's
  only one). A property test's whole value is the rule it enforces; write it down in plain
  language, not just in assertion code.

## Writing generators

Generators come from the static `Eris\Generators` factory (not free functions under
`Eris\Generator\*` — that older API shape isn't what's installed here). Verified against the
installed version (`giorgiosironi/eris` ^1.1):

- Scalars: `Generators::string()`, `::int()`, `::nat()`, `::pos()`, `::neg()`, `::bool()`,
  `::float()`, `::choose($min, $max)`, `::constant($value)`, `::elements(...$values)`.
- Combinators: `::oneOf(...$generators)` (pick one generator, equal probability),
  `::map(callable $fn, Generator $g)` (transform a generator's output),
  `::suchThat(callable $filter, Generator $g)` (filter a generator's output — has a bounded
  retry count, so don't filter out most of the domain),
  `::tuple($g1, $g2, ...)` (combine several generators into one array-of-N generator).
- Collections: `::vector($size, $g)` (fixed-length array), `::seq($g)` (variable-length array,
  can be empty — see the equalsAny pitfall below).
- A private helper method returning a `Generator` needs
  `@phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a
  PHPStan-compatible one)` on the method — Eris's `Generator` interface is Psalm-templated,
  not PHPStan-templated, so PHPStan can't verify the type parameter either way.

Two concrete pitfalls already found while building this suite, worth designing generators
around from the start:

- If a value must be non-empty, or must never coincide with values production code special-
  cases (e.g. the literal string `"0"`, which PHP's `array_filter()` treats as falsy), build
  that into the generator itself (e.g. prefix `Generators::string()` output with a fixed
  character) rather than relying on `suchThat()` alone.
- `Generators::seq($g)` can generate a zero-length array. If the code under test rejects an
  empty collection as invalid, use `Generators::vector($n, $g)` with a fixed `$n >= 1` instead,
  or you'll get spurious failures unrelated to the property you're actually testing.

## Don't narrow a generator to dodge a real finding

If a generated input reveals a genuine bug, that's the test working. Do not adjust the
generator, weaken the assertion, or otherwise make the failure disappear as a first response —
report it. Two legitimate ways to close it out once it's understood:

- **The test's own oracle is wrong** (a false positive) — fix the assertion to actually check
  what it claims to check. This happened once: an early version of `HtmlSanitizerTest` (under
  `tests/fuzz`) used a plain regex over the raw output string, which couldn't tell inert
  leftover text from a live attribute on a parsed element. The fix was to parse the output and
  inspect real attribute nodes, not to loosen the regex.
- **The finding is real but genuinely out of scope for this change** — exclude that input shape
  from the generator, but leave a comment explaining what was excluded and why, and (when
  practical) a commented-out regression test ready to uncomment once the underlying bug is
  fixed. See the `equalsAny` exclusions in `tests/fuzz/.../QueryStringParserTest.php` and
  `tests/fuzz/.../FieldSerializerTest.php` for the pattern — both leave the finding visible in
  the code, not just in a commit message.

When the finding is real and in scope, prefer leaving the test failing (as
`FieldSerializerTest::testRejectsASingleIdentifierContainingThePipeDelimiter`, under
`tests/fuzz`, currently does) over silently adjusting it, until a human decides how the
production code should behave.

## Running and reproducing

```bash
vendor/bin/phpunit --testsuite fuzz                        # whole suite
vendor/bin/phpunit tests/fuzz/.../FooTest.php               # one file
vendor/bin/phpunit --testsuite fuzz --filter FooTest        # by name - the testsuite filter
                                                             # matters, since the class name
                                                             # alone also matches tests/unit's
                                                             # FooTest for the same class
ERIS_SEED=<seed> vendor/bin/phpunit --filter '<Class>::<method>'   # reproduce a specific failure
```

A failing test prints `Reproduce with: ERIS_SEED=... vendor/bin/phpunit --filter '<Class>::
<method>'` the moment it fails — that line is `echo`'d directly (not part of the final
`FAILURES!` summary), so with several failing tests in one run, look for it right after each
test's own progress indicator, not bunched at the end. Each printed command is already scoped
to the one test it belongs to.

## Adding a new component

1. Confirm it fits the "good target" criteria in `tests/fuzz/README.md`.
2. Write the test as described above: no `#[CoversClass]`, invariant stated in a docblock,
   generators built to avoid the pitfalls above.
3. Run it repeatedly across different random seeds locally (there's no fixed seed by default)
   before treating it as stable — a generator that only fails 1-in-20 runs is still a real
   finding, not noise.
4. Add a row to the table in `tests/fuzz/README.md`.
5. Lint as normal: `composer cs`, `composer phpstan`.
