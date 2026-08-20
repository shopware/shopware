---
name: shopware-phpunit-tests
description: Write or update Shopware PHPUnit tests. Use when adding or changing tests under tests/ (unit, integration, migration, BC) — including data providers, feature flags, and coverage annotations.
license: MIT
---

# Shopware PHPUnit Tests

Tests should read like executable examples.

## Test Shape

- Write test methods as clear executable examples of the behavior under test: scenario-specific setup, action, and assertions should be easy to follow in the test body.
- Prefer explicit scenario setup over hidden mutation in fixture factories. Helper methods should create entities, files, or value objects; the test body should perform meaningful scenario wiring when that wiring helps explain the behavior under test.
- Move stable boilerplate such as mock services, the class under test, command testers, and temporary project directories into `setUp()` / `tearDown()` when that lets concrete tests focus on the scenario-specific data and execution.
- Put reusable fixture collaborators in `setUp()` when helper methods or getters may be called more than once in a test and callers should observe the same instance or state, for example registries, containers, command testers, shared filesystem roots, or other idempotent lookup objects. Keep per-scenario mutations in the test body or explicit helper parameters, but do not hide repeated construction in a getter when identity or accumulated setup matters.
- For unit tests around file access, choose the lightest setup that still reads naturally: simple single-file reads/writes can use Symfony `Filesystem` injected into the class and mocked in the test; when the scenario needs several consecutive filesystem calls, realistic paths, or directory structure, prefer committed `_fixtures` over building temp files at runtime or over-mocking the filesystem.
- When a test must really write to disk, use the Symfony `Filesystem` component instead of raw `mkdir`/`file_put_contents`/`unlink`/`rmdir`.
- Keep test helpers smaller than the code they replace.
- Name the arguments when calling a test data builder or helper with bare literals: `->review(title: 'a', content: 'b', points: 0, status: false, customerId: $id)` says what `0` and `false` mean, and lets you drop the defaults you only passed to reach a later parameter.
- A dummy entity definition, stub subscriber, or other fixture class used by exactly one test file belongs in that file, below the test class. Move it into a shared `_fixtures` namespace once a second test needs it, so that namespace means "reused" rather than "test-only".
- Do not hide assertions or feature-flag toggling behind abstractions when direct assertions are just as readable.
- Prefer one focused test per distinct exception or behavior over broad data providers when each case has its own meaning.
- Do not invoke private or protected methods of Shopware classes via reflection (`->invoke()`, `->invokeArgs()`, `setAccessible()`). Test the behavior through the public API, or restructure the code (e.g. extract the logic into a collaborator with a public contract) so it is testable without reflection. Fix legacy usages when touching such a test. Reflecting into a third-party class stays acceptable when a vendor API leaves no other option, and reading metadata from a reflection object is always fine, for example asserting a declaring class, a signature, or an attribute. The PHPStan rule `shopware.reflectionOnNonPublicMethod` enforces this.

## Never Terminate The Test Process

- A test must never let production or framework code call `exit()`, `die()`, or `posix_kill()`. PHPUnit is gone before it can report anything, so the remaining tests silently do not run and no JUnit or coverage report is written. See [issue #18661](https://github.com/shopware/shopware/issues/18661).
- `Shopware\Core\Test\PHPUnit\CompletionGuard` (registered from `TestBootstrapper::bootstrap()`) now turns this into a loud failure instead of a green run. `PHPUnit terminated before the test runner finished the suite` on `STDERR` with exit code `1` and no failure summary means a test killed the process — find the last test that started, not a failing assertion.
- When testing a Symfony console `Application`, always call `$application->setAutoExit(false)` before running it. `Application::run()` ends in `exit($code)` otherwise. `CommandTester` is unaffected — it invokes the command directly — so prefer it over `ApplicationTester` unless the scenario genuinely needs the application layer (command resolution, aliases, global options).
- The same applies to any code path that reaches `exit()`: kernel shutdown handlers, `Process` wrappers configured to exit, and CLI entry-point scripts included in a test. Cover the callable underneath instead of the script.

## Assertions And Fixtures

- Prefer `expectExceptionObject()` over a broader `expectException`, build the expected exception through the same domain factory when one exists so class, code, and message stay aligned with production behavior.
- Do not behavior-mock Doctrine DBAL `Connection` in unit tests by asserting SQL calls or parameters. Stub DBAL-consuming collaborators when needed; isolate SQL/DBAL adapters and cover those adapters with integration tests.
- Exception, when the behaviour under test is a decision whose only observable effect is the write itself and the class offers no other seam: drive the public method, stub the read side for data only, capture the executed statements in one helper, and assert on the written values in domain terms, never on SQL text. Make the `Connection` double's `transactional()` actually invoke its closure, otherwise nothing executes and the tests pass vacuously. See "Asserting writes when there is no other seam" in `coding-guidelines/core/unit-tests.md`.

## Stubbing DAL Repositories

- Stub DAL repositories with `Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository`, not with a mock of `EntityRepository`.
- Do not add a `/** @var StaticEntityRepository<FooCollection> */` annotation above the construction. The generic is inferred from the constructor when the searches contain a typed collection or `EntitySearchResult`.
- When no search carries the type — empty searches, plain id lists for `searchIds()`, callables — bind the generic with the factory instead: `StaticEntityRepository::of(FooCollection::class, $searches)`.
- Build search results with the concrete collection class the consumer expects (`new AppCollection([...])`, not `new EntityCollection([...])`); a generic `EntityCollection` infers the wrong type and fails against `EntityRepository<AppCollection>` parameters.
- Class properties holding the stub keep their `@var StaticEntityRepository<FooCollection>` docblock — that is the property's type declaration, not an inference crutch.

## Feature Flags And Coverage

- Keep legacy feature-flag behavior in dedicated tests that are easy to remove when the flag is removed.
- In unit tests, current major feature flags are active by default. Test legacy/off behavior by disabling the flag with the `#[DisabledFeatures]` attribute; do not use `Feature::fake()` just to activate the current major flag.
- In integration tests, feature-flag state comes from the job configuration (the default integration job runs with flags off, integration-major with `FEATURE_ALL=major`), and the suite may run multiple times with flags on and off. `#[DisabledFeatures]` has no effect there and the test runner rejects it — a test carrying the attribute fails the run. Skip tests explicitly with `Feature::skipTestIfActive()` or `Feature::skipTestIfInActive()` when the current feature-flag value is not the one the scenario expects.
- If a class is intentionally covered only by integration tests, mark it with `@codeCoverageIgnore` on its own docblock line and add a separate `@see \Shopware\Tests\Integration\…\DedicatedIntegrationTest` line. Use the fully qualified class name with a leading `\`; do not import a test class solely for the annotation. The referenced class must be a dedicated integration test for that production class; incidental coverage from an unrelated test is not sufficient. Extract or add a focused test class before adding the annotation.
- Every new class should either have focused unit-test coverage or be explicitly marked with `@codeCoverageIgnore` and an integration-test `@see` when unit coverage does not make sense.
- Simple struct-style classes with only public properties do not need unit tests; mark them with `@codeCoverageIgnore` instead.
- Do not add `#[CoversClass]`, `#[CoversFunction]`, or `#[CoversNothing]` to integration tests. Shopware's PHPStan rule allows those attributes only on unit and migration tests.
- Declare exactly one `#[CoversClass]` per test file: the covered class decides which domain owns the test. When a second class needs tests, create a second test file. A Danger rule fails new test files covering more than one class.

## Meta-information of test classes

- Give every test class a `#[Package('…')]` attribute (import `Shopware\Core\Framework\Log\Package`) so failing CI jobs — especially the nightlies — can be routed to the owning domain team. A Danger rule fails PRs that add test classes without it.
- In unit and migration tests, copy the value from the `#[CoversClass]` target's `#[Package]`. A PHPStan rule (`TestPackageMatchRule`) fails on mismatches; `fundamentals@<area>` counts as equal to `<area>`.
- Integration tests carry no `#[CoversClass]`; use a `#[Package]` value that occurs in the `src/` directory the test path mirrors (e.g. `tests/integration/Core/Checkout/Cart/…` → `src/Core/Checkout/Cart`). The same PHPStan rule fails when the value matches none of the packages found there.
- When a change moves the covered class to another package, update the test's attribute in the same change so the two stay in sync.
- Every test class needs to be marked as internal with `@internal` PHPDoc class annotation.

## Data Providers

- Use named `yield` cases in unit-test data providers instead of returning arrays, even for small providers. This keeps cases readable and avoids materializing large arrays as providers grow.
- Do not use `yield from` with an inline array for providers. Prefer one explicit `yield 'human readable case description' => [...]` per scenario.
- Provider case names should explain the scenario and expected behavior, not mechanically restate raw input values. Good names mention the rule being proven, such as priority, normalization, timezone conversion, or boundary handling.
- Be conservative when deleting "duplicate" provider cases. Remove only exact semantic duplicates that add no coverage, and keep similar-looking cases when they cover distinct edge behavior.
- Fold two tests into one provider when they differ only in their input and their expectation; pass the discriminating value together with the expectation instead of copying the whole scenario. This does not override the rule above about keeping one focused test per distinct behavior: a case that carries its own meaning stays its own test.

## Detailed Guidelines

- Read `coding-guidelines/core/unit-tests.md` when writing or restructuring PHP unit tests.
- Read `coding-guidelines/core/writing-code-for-static-analysis.md` when test code interacts with PHPStan-sensitive types, assertions, or generics.
- Read `coding-guidelines/core/feature-flags.md` when testing feature-flagged current or legacy behavior.
