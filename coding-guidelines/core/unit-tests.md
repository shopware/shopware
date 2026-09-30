# Unit tests

Unit tests are an essential part of our software. The Shopware product grows and grows, the release cycles become shorter and shorter, more and more developers work with the software.

**Therefore it is important that all functionalities and services are fully unit tested.**

When writing unit tests, the following is important:

- **"100% coverage "** - This does not mean that simply a high code coverage should be generated, but that all use cases of each individual service is tested.
- **Package ownership** - Every test class must declare `#[Package('…')]` with the same package as its covered production domain so CI failures can be routed to the owning team.
- **One covered class per test file** - Declare exactly one `#[CoversClass]`. The covered class decides which domain owns the test; several covered classes make the ownership ambiguous and usually mean several subjects share one file. Split it. A Danger rule fails new test files covering more than one class.
- **Source-file coverage** - New source files should either have focused unit-test coverage or be explicitly marked with `@codeCoverageIgnore` when they are intentionally covered by integration tests. Add a separate `@see \Shopware\Tests\Integration\…\DedicatedIntegrationTest` line in the class docblock. Use the fully qualified class name with a leading `\`; do not import a test class solely for the annotation. The referenced test must be dedicated to that production class; do not point to an unrelated test that only covers the class incidentally. Extract or add a focused integration test first. `CodeCoverageIgnoreEvaluationRule` rejects the annotation on any method that does more than pass a value through: branching and error paths, value mutation (`unset`, compound assignment such as `+=` or `.=`, increments, a second write to the same local), discarded calls on `$this`, `self::` or `static::` such as access guards or hooks, discarded calls on a parameter (`$criteria->addFilter(...)`, the method shaping its input; `EntityExtension::extendFields()` is exempt as schema declaration, like `defineFields()`), and constructors that configure their parent (a literal handed to `parent::__construct()`, or a parameter default the parent does not share). A `@see` to a dedicated `\Shopware\Tests\Integration\…` or `\Shopware\Tests\DevOps\…` test lifts that check.
- **No process termination** - A test must never let code under test call `exit()` or `die()`. PHPUnit dies with it and every later test silently does not run. When testing a Symfony console `Application`, call `$application->setAutoExit(false)` before running it, or use `CommandTester`, which invokes the command directly. `Shopware\Core\Test\PHPUnit\CompletionGuard` fails such a run instead of letting it pass green — see [issue #18661](https://github.com/shopware/shopware/issues/18661) and [`ci-workflows.md`](ci-workflows.md).
- **Performance** - As we grow more and more it is advisable to pay attention to the speed of the tests.
- **Mocking and stubbing** - Use mocks and stubs intentionally to keep unit tests fast and focused. Simple stubs such as `createStub()` or concrete test doubles like `StaticEntityRepository` are fine when a dependency only needs to return data. Do not behavior-mock Doctrine DBAL `Connection` by asserting SQL calls or parameters; isolate SQL/DBAL work in database adapters and cover those adapters with integration tests. When the behaviour under test has no observable effect other than the write itself, see "Asserting writes when there is no other seam" below.
- **createMock() only with expectations** - A double that never receives `->expects()` is a stub, so create it with `createStub()`. `NoCreateMockWithoutExpectationsRule` reports the ones that are provably never expected, including doubles handed to a fixture helper that only forwards them into the subject under test. Two API details matter when converting: `->with()` exists on mocks only, so a stub asserts its arguments inside `willReturnCallback()` instead, and a fixture helper receiving a stub must type that parameter as `Stub` (`Foo&Stub`) rather than `MockObject`. Both slip past PHPUnit at runtime and only fail in PHPStan.
- **Readable** - You are not the only one who maintains the code. Therefore, it is important that others can quickly and easily understand your unit tests and extend them with additional cases.
- **Named arguments for opaque values** - When a call into a test data builder or helper passes bare literals, name them. `->review(title: 'a', content: 'b', points: 0, status: false, customerId: $id)` says what `0` and `false` mean, `->review('a', 'b', 0, $salesChannelId, Defaults::LANGUAGE_SYSTEM, false, $id)` does not. Naming also lets you drop the defaults you only passed to reach a later parameter.
- **Callback assertions** - When a callback, listener, or inline test double observes the behavior under test, assert the observed arguments directly in that callback. Only keep the smallest state outside the callback that the test still needs, for example a boolean to prove it was called, a counter when cardinality matters, or captured values when later assertions need comparison across calls.
- **Extensibility** - It is important that when more cases are added or certain cases are not tested that it is easy to extend your unit tests with another case without extending dozens of lines of code.
- **Data providers instead of near-duplicate tests** - Two tests that differ only in their input and their expectation belong in one test with a data provider. Name every case so a failure reads as a sentence (`'pending review of another customer is hidden'`), and pass the discriminating value together with the expectation instead of copying the whole scenario. This is *Extensibility* applied: the next case should be one `yield`, not another forty lines.
- **Test-only classes live next to the test that uses them** - A dummy entity definition, stub subscriber, or other fixture class used by exactly one test file belongs in that file, below the test class. Move it into a shared `_fixtures` namespace once a second test actually needs it. That keeps the test readable in one place and keeps the shared namespace meaning "reused" rather than "test-only".
- **Modularity** - Your test should not fail just because another test left artifacts (files, storage records, ...).
- **Cleanup** - It is also important that you clean up your artifacts. If you register an event listener dynamically, make sure that it is removed again on `teardown`. If you write data to the database or change the schema, make sure it is rolled back.
- **Failure** - Don't just test the happy case or success case, test the failure of your services and objects.
- **Expected exceptions** - For deterministic exception cases, use PHPUnit's `expectExceptionObject()` (or `expectException*()` helpers) instead of manual `try/catch`. This keeps tests shorter, clearer, and less error-prone.
- **No reflective invocation of our own private or protected methods** - Do not use reflection (`->invoke()`, `->invokeArgs()`, `setAccessible()`) to call a private or protected method of a Shopware class. A non-public method is an implementation detail; test the behavior through the public API, or restructure the code (e.g. extract the logic into a collaborator with a public contract) so it is publicly testable. Reflecting into a **third-party** class is a different matter and stays acceptable when a vendor API leaves no other option. Reading metadata from a reflection object is always fine, for example asserting a declaring class, a signature, or an attribute. The PHPStan rule `shopware.reflectionOnNonPublicMethod` enforces this: it resolves the target class and the method visibility precisely and reports reflective access to any non-public method of a Shopware class. `setAccessible()` on a method is reported for any target, it has been a no-op since PHP 8.1.
- **Unit** - Write unit tests (not integration tests), don't always test the whole request or service stack, you can also just instantiate services yourself and mock dependencies to make testing faster and easier.
- **Para-test** - Your tests should be compatible with our para-test setup so that any developer can quickly run the tests locally.

## Examples
Here are some good examples of shopware unit tests:
- [CriteriaTest](https://github.com/shopware/shopware/blob/trunk/tests/unit/Core/Framework/DataAbstractionLayer/Search/CriteriaTest.php)
  - Good example for simple DTO tests
- [CashRounding](https://github.com/shopware/shopware/blob/trunk/tests/unit/Core/Checkout/Cart/Price/CashRoundingTest.php)
  - Nice test matrix for single service coverage
- [AddCustomerTagActionTest](https://github.com/shopware/shopware/blob/trunk/tests/unit/Core/Content/Flow/Dispatching/Action/AddCustomerTagActionTest.php)
  - A good example of how to test flow actions and use mocks for repositories

Here are some good examples of integration tests:
- [ProductCartTest](https://github.com/shopware/shopware/blob/trunk/src/Core/Content/Test/Product/Cart/ProductCartTest.php)
  - Slim product cart test with good helper function integrations
- [CachedProductListingRouteTest](https://github.com/shopware/shopware/blob/trunk/src/Core/Content/Test/Product/SalesChannel/Listing/CachedProductListingRouteTest.php)
  - This test is a little complex, but has a very good test case matrix with good descriptions and reusable test code.

# Mocks and its influence on software design

When speaking about unit testing, one automatically also speaks about `mocks` and the need to mock away dependencies.
It seems to be quite a common attitude towards mocks along the lines of "Mock every external dependency of the class under test" and this attitude can be quite dangerous.
Therefore, here are some words of caution.

## Mocks are hard to refactor

Be cautious when utilizing mocks extensively because it can be hard to automatically refactor classes. This is because IDEs do not provide robust support for refactoring classes that are heavily mocked, and tools like *PHPStan* may not effectively detect these mock-related issues.

More broadly speaking, it is hard to guarantee that the mock behaves in the same/or intended manner as the real implementation (especially when the underlying implementation changes).

Use mocks only where you need to because:
1. creating the objects is hard as you need tons of nested dependencies to create the object.
or
2. the class produces some side effects that you don't want in unit tests.

For all other cases, use real implementations and rely as minimally as possible on the magic of phpunit's mocking framework.

Do not behavior-mock Doctrine DBAL `Connection` in unit tests by asserting SQL calls or parameters. Classes with direct SQL/DBAL work should usually be treated as database adapters and covered with integration tests. Unit-test the surrounding business logic against a narrow repository, gateway, service abstraction, or simple stub that returns the result object the class under test needs.

### Asserting writes when there is no other seam

The rule above bans coupling a test to SQL: statement text, clause order, parameter names. It does not ban observing *that* a write happened.

Sometimes the behaviour under test is a decision whose only effect is a write, in a class that offers no other seam: the public methods return `void`, no domain event distinguishes the cases, and the collaborator performing the write is constructed inline instead of injected. The write-protection guard in `SeoUrlPersister` is the reference example — when it skips an update, an `INSERT` is not queued and nothing else observable changes.

In that situation, prefer in this order:

1. Extract the decision into a collaborator with a public contract and unit-test that collaborator.
2. Cover the behaviour in an integration test against a real database.
3. Drive the public method, stub the read side for data only, capture the executed statements, and assert on the written **values**.

Option 3 is acceptable when the first two do not fit, under these conditions:

- Assert on values, never on SQL text or parameter names, and phrase the assertion in domain terms ("no row was written for this path") rather than in SQL terms.
- Keep the statement capture in a single helper, so a query refactor touches one place instead of every test.
- Make sure the transaction wrapper actually invokes its closure. A `Connection` double whose `transactional()` ignores its argument executes nothing, so every test in the file passes without proving anything.
- Confirm each test fails when the decision under test is flipped, before trusting it.

## Focus on behavior, not implementation: Effective unit testing principles

Relying heavily on mocks creates a bad pattern in unit tests of testing `how` something is implemented and not `what` the implementation actually does. If tests are implemented in a mock-heavy way, they are tightly coupled to the implementation, meaning they rely on implementation details and may fail more often when the implementation details change than when the actual behavior of the class under test changes. Consider these two example changes to some classes:
Before:
```php
$id = $this->repository->search($criteria, $context)->first()?->getId();
```
After:
```php
$id = $this->repository->searchIds($criteria, $context)->firstId();
```
Before
```php
$values = $this->connection->fetchAllAssociative('SELECT first, second FROM foo ...');

$values = $this->mapToKeyValue($values);
```
After:
```php
$values = $this->connection->fetchKeyValue('SELECT first, second FROM foo ...');
```

By definition, both changes are a pure example of refactoring::
>Refactoring is a disciplined technique for restructuring an existing body of code, altering its internal structure without changing its external behavior.
-> [Martin Fowler](https://refactoring.com/)

But when the unit test mocked the `repository` or `connection` dependencies the unit tests will fail after the change, even though the external behaviour (that's what a test should really test) was not changed.

Using mocks is ok in some cases, but not all.
Mocking a narrow repository-like abstraction can be valid when it keeps the unit test focused on behavior. Mocking low-level DBAL calls is an anti-pattern because it couples the test to SQL implementation details; cover that adapter with an integration test instead.
Furthermore, the intention of this document is to keep you aware of the downsides that come with using mocks.

## Mocks might indicate your class is not well-designed

In a well-designed and testable system, it is relatively easy to isolate individual classes or modules and distinguish them between the components that contain the core business logic, which should be extensively unit tested, and the portions responsible for interfacing with the external environment and generating side effects. These side-effect-prone elements should be substituted in the unit tests. In fact, it is advisable to perform integration testing since their primary purpose is to abstract and facilitate the replacement of side effects in tests.

This kind of abstraction follows when you apply the principles of [Domain Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html) and [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture) (aka Ports & Adapters)

The absence of such abstraction in the existing `shopware/platform` codebase is one of the reasons why it is so hard to write "good" unit tests for shopware, but that does not mean that we should keep designing our code as we used and keep writing "bad" (meaning unit tests tightly coupled to the implementation) unit tests.
However, it's the opposite; we start designing our code in a way that makes it easy to write "good" unit test that does not rely that much on a "magic" mock framework.

So, a heavy reliance on mocks when writing unit tests can indicate a potential issue with the software design, suggesting insufficient encapsulation. Hence designing code to promote better encapsulation and reduce the need for extensive mocking is advisable. This can lead to improved testability and overall software quality.

## Better options than mocks

There are better options but that depends on the use cases. Here are a few alternatives:

1. Use the real implementation (this means the real thing is easy to create and does not produce side effects)
2. Use a hand-crafted dummy implementation of the real thing, that is easy to configure and behaves like a stub in that use case (this means that the real thing probably needs to be designed in a way to be easy to replace, examples of this in our test suite are the `StaticEntityRepository` or `StaticSystemConfigService`)
3. Fallback to using phpunit's mocking framework (when the real thing is not designed to be replaced easily)

The way you design your codebase directly impacts whether you can rely on option 1 or option 2 without resorting to heavy mocking.

# Conclusion: Write tests first!

When you write tests first, most of the points described above should come out of the box!
Nobody who starts with a test would start with configuring a mock.

While we provide insights on this, it is essential to validate the information. So we encourage you to explore the following references to gain a deeper understanding and form your own opinion.

## Related ADRs

- [Test structure](../../adr/2022-10-20-test-structure.md)
- [Follow test pyramid](../../adr/2023-02-13-follow-test-pyramid.md)
- [Mocking repositories](../../adr/2023-04-01-mocking-repositories.md)

## References

Frank De Jonge on the exact same topic (with more examples in PHP): https://blog.frankdejonge.nl/testing-without-mocking-frameworks/

Martin Fowler on the differences between mocks (option 3) and stubs (option 2): https://martinfowler.com/articles/mocksArentStubs.html

Presentation by Mathias Noback on testing hexagonal architectures: https://matthiasnoback.nl/talk/a-testing-strategy-for-hexagonal-applications/

Some good real life examples on unit tests in php: https://github.com/sarven/unit-testing-tips

A great write up on testing in general: https://dannorth.net/2021/07/26/we-need-to-talk-about-testing/

Quite old (1997!) paper on how **not** to use code coverage: http://www.exampler.com/testing-com/writings/coverage.pdf

Great blog post series on how to avoid mocks: https://philippe.bourgau.net/categories/#how-to-avoid-mocks-series
