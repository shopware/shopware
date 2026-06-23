---
name: shopware-phpunit-tests
description: Write or update Shopware PHPUnit tests. Use when adding unit, integration, migration, or BC tests; working with data providers, feature flags, filesystem scenarios, Doctrine DBAL boundaries, coverage annotations, or exception assertions.
---

# Shopware PHPUnit Tests

Tests should read like executable examples.

## Test Shape

- Keep scenario-specific setup, action, and assertions visible in the test body.
- Move stable boilerplate into `setUp()` / `tearDown()` only when it lets tests focus on scenario data.
- Use helpers to create entities, files, or value objects; do not hide assertions or feature-flag toggling in helpers.
- Keep test helpers smaller than the code they replace.
- Prefer one focused test per distinct exception or behavior over broad data providers when each case has its own meaning.

## Assertions And Fixtures

- Prefer `expectExceptionObject()` and build the expected exception through the same domain factory when one exists.
- For file access, use the lightest setup that still reads naturally: Symfony `Filesystem` mocks for simple single-file reads/writes, committed `_fixtures` for realistic multi-file scenarios.
- Do not behavior-mock Doctrine DBAL `Connection`; stub DBAL-consuming collaborators or move SQL to an adapter covered by integration tests.

## Feature Flags And Coverage

- In unit tests, current major feature flags are active by default; use `#[DisabledFeatures]` for legacy/off behavior.
- In integration tests, skip explicitly with `Feature::skipTestIfActive()` or `Feature::skipTestIfInActive()` when a specific feature-flag state is required.
- Every new class needs focused unit coverage or `@codeCoverageIgnore` plus `@see ShortIntegrationTestClassName` when only integration coverage makes sense.
- Simple public-property structs can use `@codeCoverageIgnore`.
- Do not add `#[CoversClass]`, `#[CoversFunction]`, or `#[CoversNothing]` to integration tests.

## Data Providers

- Use named `yield` cases.
- Do not use `yield from` with inline arrays.
- Name cases by the rule or boundary being proven, not raw input values.
- Delete only exact semantic duplicates.

## Detailed Guidelines

- Read `coding-guidelines/core/unit-tests.md` when writing or restructuring PHP unit tests.
- Read `coding-guidelines/core/writing-code-for-static-analysis.md` when test code interacts with PHPStan-sensitive types, assertions, or generics.
- Read `coding-guidelines/core/feature-flags.md` when testing feature-flagged current or legacy behavior.
