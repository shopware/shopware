---
guide: tests-for-behavior-change
title: Does a test prove the change, and would it fail without it?
personas: [architecture]
rules:
  - { id: TEST-001, since: 2026-10-09, source: "AGENTS.md#definition-of-done--mandatory-for-every-change", fixture: "tests/guide/tests-for-behavior-change/catch" }
  - { id: TEST-002, since: 2026-10-09, source: "coding-guidelines/core/unit-tests.md#asserting-writes-when-there-is-no-other-seam", fixture: "" }
  - { id: TEST-003, since: 2026-10-09, source: "adr/2023-02-13-follow-test-pyramid.md#decision", fixture: "" }
  - { id: TEST-004, since: 2026-10-09, source: "coding-guidelines/core/unit-tests.md#mocks-are-hard-to-refactor", fixture: "" }
  - { id: TEST-005, since: 2026-10-09, source: ".agents/skills/shopware-phpunit-tests/SKILL.md#test-shape", fixture: "" }
  - { id: TEST-006, since: 2026-10-09, source: ".agents/skills/shopware-phpunit-tests/SKILL.md#test-shape", fixture: "" }
  - { id: TEST-007, since: 2026-10-09, source: ".agents/skills/shopware-phpunit-tests/SKILL.md#data-providers", fixture: "" }
  - { id: TEST-008, since: 2026-10-09, source: "coding-guidelines/core/unit-tests.md#unit-tests", fixture: "" }
  - { id: TEST-009, since: 2026-10-09, source: ".agents/skills/shopware-phpunit-tests/SKILL.md#feature-flags-and-coverage", fixture: "" }
  - { id: TEST-010, since: 2026-10-09, source: "AGENTS.md#definition-of-done--mandatory-for-every-change", fixture: "" }
---

## Why this guide exists

"Please add a test" is the most frequent review request in the repository (about 50 comments between April and October 2026), and the second most frequent is "this test cannot fail". A fix without a test comes back with the next refactoring; a test that passes without the fix only looks like coverage. This guide also keeps tests cheap: the right suite, few mocks, no duplicates.

## Check

- **TEST-001** Look for a behaviour change or bug fix in `src/` with no test that pins it: a new branch, guard, edge case or error path. The bug returns unnoticed with the next refactoring. Ask for a test that fails without the change; when a PR deletes a test, its cases must stay covered elsewhere. Rule: [Definition of Done](../../../../AGENTS.md#definition-of-done--mandatory-for-every-change). Example: a fix was approved and merged without a test; a flow test chose a rule that passed even without the change; an SEO URL persister fix came without a case for the reported issue.
- **TEST-002** Look for tests that cannot fail: they assert a value the test itself set or the mock returns, accept any 4xx or 5xx, or skip the intended branch because a precondition is missing. Such a test stays green when the behaviour breaks. Assert the exact outcome and the claimed effect (query skipped, error thrown). Rule: [Asserting writes when there is no other seam](../../../../coding-guidelines/core/unit-tests.md#asserting-writes-when-there-is-no-other-seam), which asks to confirm that each test fails when the decision under test is flipped. Example: a captcha listener test missed a precondition, so the code exited early and the test passed for the wrong reason; a sidebar test set a store value and asserted that same value; a search test did not prove that the database query was skipped.
- **TEST-003** Look at the suite: an integration test for logic that needs neither container nor database. It is slow and does not count toward unit coverage. Use a unit test with stubs; keep integration tests for real wiring. Rule: [ADR Follow test pyramid](../../../../adr/2023-02-13-follow-test-pyramid.md#decision). Example: double opt-in service cases, a delivery calculator test and a theme lifecycle exception check ran as integration tests although they needed neither container nor database.
- **TEST-004** Look for mock-heavy tests of persistence: a mocked DBAL `Connection` that asserts SQL, or mock setup larger than the logic. They break on every refactoring and never run the real query. Cover SQL with an integration test, use `createStub()` or `StaticEntityRepository` for data-only doubles. Rule: [Mocks are hard to refactor](../../../../coding-guidelines/core/unit-tests.md#mocks-are-hard-to-refactor), [Better options than mocks](../../../../coding-guidelines/core/unit-tests.md#better-options-than-mocks). Example: a version manager test had over 100 lines of mock setup, changed promotion queries ran only against mocks, and an accept-all rate limiter mock never ran the real limiter.
- **TEST-005** Look for `IntegrationTestBehaviour` or new test traits where `KernelTestBehaviour` plus `DatabaseTransactionBehaviour` would do. Every extra trait boots or resets more than the test needs. Use only the traits needed; share setup through a static helper or base class. Rule: [Test Shape](../../shopware-phpunit-tests/SKILL.md#test-shape). Example: a domain resolver test, an app feature storage test and an SEO URL lifecycle test used the kitchen-sink trait instead of only the traits they needed.
- **TEST-006** Look for unit tests that write files, open a database connection, or read private members through reflection. They are order-dependent and test implementation instead of behaviour. Use committed `_fixtures`, in-memory adapters, or the public API. Rule: [Test Shape](../../shopware-phpunit-tests/SKILL.md#test-shape), [Unit tests](../../../../coding-guidelines/core/unit-tests.md#unit-tests). Example: a variant listing updater unit test used a real connection, an entity generator test wrote to the filesystem, and a Twig runtime test read a private property.
- **TEST-007** Look for near-duplicate test methods or providers that `return` arrays. Use one test with named `yield` cases, or `#[TestWith]` for a few trivial scalar cases. Rule: [Data Providers](../../shopware-phpunit-tests/SKILL.md#data-providers); `#[TestWith]`: guideline paragraph pending. Example: a payment method rule provider returned an array instead of using `yield`; an object utils spec repeated the same content instead of using a data provider.
- **TEST-008** Look for `time()`, `new \DateTime()` or `now` in tests, or `anything()` where a time is asserted. The test is flaky around midnight and cannot assert exact values. Freeze time with `MockClock` or `ClockSensitiveTrait`. Rule: guideline paragraph pending ([Unit tests](../../../../coding-guidelines/core/unit-tests.md#unit-tests) only lists `ClockSensitiveTrait` as allowed). Example: a time range rule test used the current time instead of a fixed date, a cache store test could have asserted exact values with `MockClock`, and a subscriber test read the real time although `MockClock` exists.
- **TEST-009** Look for duplicate coverage: an integration test for cases the unit test already covers, tests for plain DTOs or what types guarantee, or assertions repeating `expectExceptionObject()`. Each costs CI time and maintenance and proves nothing new. Rule: [Feature Flags And Coverage](../../shopware-phpunit-tests/SKILL.md#feature-flags-and-coverage). Example: a plugin service integration test repeated cases the unit test already covered; a route response DTO and a promotion error DTO got tests that only add maintenance.
- **TEST-010** Look for a PHP integration test that only renders a Storefront Twig template for a Twig-only change. It can cache empty request globals in the shared kernel and break unrelated tests. Run the Twig lint; use acceptance tests when the rendering matters. Rule: [Definition of Done](../../../../AGENTS.md#definition-of-done--mandatory-for-every-change). Example: a pagination template change and a product feature template change added PHP integration tests that rendered Twig; the second only stayed stable because it reset shared state first.

## Do not flag

### CI covers

- Danger `MissingUnitTests` (a new `src/` class without a unit test or `@codeCoverageIgnore`), `TraitUsageInNewUnitTests`, `SingleCoversClassInTests`, `MissingPackageAttributeInTests`. Report TEST-001 for changed existing classes, which Danger does not see.
- PHPStan `Rules/Tests`: `NoCreateMockWithoutExpectationsRule` (provable mocks without expectations), `NoKernelInUnitTestsRule` and `NoKernelLifecycleManagerInUnitTestsRule` (kernel or database in unit tests), `NoReflectionInUnitTestsRule` and `NoReflectionOnNonPublicMethodsRule`, `NoExpectExceptionMessageRule`, `NoAnyInvocationMatcherRule`, `NoUnreachableAssertionAfterExpectExceptionRule`, `NoFeatureSkipInUnitTestsRule`, `DataProviderRowArityRule`, `CoversAttributeRule`, `TestPackageMatchRule`; `CodeCoverageIgnoreEvaluationRule` for misused `@codeCoverageIgnore`.

### Legitimate patterns

- No test for docs, snippets, styles, Twig-only template changes, generated files, or pure renames.
- `@codeCoverageIgnore` with a `@see` to a dedicated integration test, and struct classes with only public properties.
- A mock with `expects()` where the call itself is the behaviour (an event is dispatched, a message is sent).
- Administration Jest tests: the same rules apply in spirit, but the Admin JS guidance owns their details.

## Severity

- `major`: a bug fix or behaviour change without a test that pins it, or a test that cannot fail. The regression comes back unnoticed. Also a Twig render test (TEST-010), which can break unrelated tests.
- `minor`: wrong suite, mock-heavy setup, extra traits, real time, missing provider, duplicate coverage.
- `blocking`: never from this guide. A missing test alone does not make a merge unsafe.

## Retired

(none yet)
