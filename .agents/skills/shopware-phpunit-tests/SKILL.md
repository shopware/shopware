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
- Keep test helpers smaller than the code they replace.
- Do not hide assertions or feature-flag toggling behind abstractions when direct assertions are just as readable.
- Prefer one focused test per distinct exception or behavior over broad data providers when each case has its own meaning.

## Assertions And Fixtures

- Prefer `expectExceptionObject()` over a broader `expectException`, build the expected exception through the same domain factory when one exists so class, code, and message stay aligned with production behavior.
- Do not behavior-mock Doctrine DBAL `Connection` in unit tests by asserting SQL calls or parameters. Stub DBAL-consuming collaborators when needed; isolate SQL/DBAL adapters and cover those adapters with integration tests.

## Feature Flags And Coverage

- Keep legacy feature-flag behavior in dedicated tests that are easy to remove when the flag is removed.
- In unit tests, current major feature flags are active by default. Test legacy/off behavior by disabling the flag with the `#[DisabledFeatures]` attribute; do not use `Feature::fake()` just to activate the current major flag.
- In integration tests, the suite may run multiple times with feature flags on and off. Do not use `#[DisabledFeatures]` there for simple legacy/current branching; skip tests explicitly with `Feature::skipTestIfActive()` or `Feature::skipTestIfInActive()` when the current feature-flag value is not the one the scenario expects.
- If a class is intentionally covered only by integration tests, mark it with `@codeCoverageIgnore` on its own docblock line and add a separate `@see ShortIntegrationTestClassName` line. Import the integration test class with a `use` statement instead of writing a fully-qualified class name in the annotation.
- Every new class should either have focused unit-test coverage or be explicitly marked with `@codeCoverageIgnore` and an integration-test `@see` when unit coverage does not make sense.
- Simple struct-style classes with only public properties do not need unit tests; mark them with `@codeCoverageIgnore` instead.
- Do not add `#[CoversClass]`, `#[CoversFunction]`, or `#[CoversNothing]` to integration tests. Shopware's PHPStan rule allows those attributes only on unit and migration tests.

## Data Providers

- Use named `yield` cases in unit-test data providers instead of returning arrays, even for small providers. This keeps cases readable and avoids materializing large arrays as providers grow.
- Do not use `yield from` with an inline array for providers. Prefer one explicit `yield 'human readable case description' => [...]` per scenario.
- Provider case names should explain the scenario and expected behavior, not mechanically restate raw input values. Good names mention the rule being proven, such as priority, normalization, timezone conversion, or boundary handling.
- Be conservative when deleting "duplicate" provider cases. Remove only exact semantic duplicates that add no coverage, and keep similar-looking cases when they cover distinct edge behavior.

## Detailed Guidelines

- Read `coding-guidelines/core/unit-tests.md` when writing or restructuring PHP unit tests.
- Read `coding-guidelines/core/writing-code-for-static-analysis.md` when test code interacts with PHPStan-sensitive types, assertions, or generics.
- Read `coding-guidelines/core/feature-flags.md` when testing feature-flagged current or legacy behavior.
