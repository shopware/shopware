# Unit tests

Unit tests are an essential part of our software. The Shopware product grows and grows, the release cycles become shorter and shorter, more and more developers work with the software.

**Therefore it is important that all functionalities and services are fully unit tested.**

When writing unit tests, the following is important:

- **"100% coverage "** - This does not mean that simply a high code coverage should be generated, but that all use cases of each individual service is tested.
- **Performance** - As we grow more and more it is advisable to pay attention to the speed of the tests.
- **Mocking** - Don't be lazy but deal with mock objects to optimize for example database access. So you don't have to persist every storage case before but you can describe it as a Mock.
- **Readable** - You are not the only one who maintains the code. Therefore, it is important that others can quickly and easily understand your unit tests and extend them with additional cases.
- **Extensibility** - It is important that when more cases are added or certain cases are not tested that it is easy to extend your unit tests with another case without extending dozens of lines of code.
- **Modularity** - Your test should not fail just because another test left artifacts (files, storage records, ...).
- **Cleanup** - It is also important that you clean up your artifacts. If you register an event listener dynamically, make sure that it is removed again on `teardown`. If you write data to the database or change the schema, make sure it is rolled back.
- **Failure** - Don't just test the happy case or success case, test the failure of your services and objects.
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
2. the class produces some side effects that you don't want in unit tests (e.g., DB writes).

For all other cases, use real implementations and rely as minimally as possible on the magic of phpunit's mocking framework.

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
Probably the examples from above are ones that are totally valid (as the mocked classes rely on a DB), which can be commonly encountered in real life.
Furthermore, the intention of this document is to keep you aware of the downsides that come with using mocks.

## Mocks might indicate your class is not well-designed

In a well-designed and testable system, it is relatively easy to isolate individual classes or modules and distinguish them between the components that contain the core business logic, which should be extensively unit tested, and the portions responsible for interfacing with the external environment and generating side effects. These side-effect-prone elements should be substituted in the unit tests. In fact, it is advisable to perform integration testing since their primary purpose is to abstract and facilitate the replacement of side effects in tests.

This kind of abstraction follows when you apply the principles of [Domain Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html) and [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/) (aka Ports & Adapters)

The absence of such abstraction in the existing `shopware/platform` codebase is one of the reasons why it is so hard to write "good" unit tests for shopware, but that does not mean that we should keep designing our code as we used and keep writing "bad" (meaning unit tests tightly coupled to the implementation) unit tests.
However, it's the opposite; we start designing our code in a way that makes it easy to write "good" unit test that does not rely that much on a "magic" mock framework.

So, a heavy reliance on mocks when writing unit tests can indicate a potential issue with the software design, suggesting insufficient encapsulation. Hence designing code to promote better encapsulation and reduce the need for extensive mocking is advisable. This can lead to improved testability and overall software quality.

## Better options than mocks

There are better options but that depends on the use cases. Here are a few alternatives:

1. Use the real implementation (this means the real thing is easy to create and does not produce side effects)
2. Use a hand-crafted dummy implementation of the real thing, that is easy to configure and behaves like a stub in that use case (this means that the real thing probably needs to be designed in a way to be easy to replace, examples of this in our test suite are the `StaticEntityRepository`  or `StaticSystemConfigService`)
3. Fallback to using phpunit's mocking framework (when the real thing is not designed to be replaced easily)

The way you design your codebase directly impacts whether you can rely on option 1 or option 2 without resorting to heavy mocking.

# Conclusion: Write tests first!

When you write tests first, most of the points described above should come out of the box!
Nobody who starts with a test would start with configuring a mock.

While we provide insights on this, it is essential to validate the information. So we encourage you to explore the following references to gain a deeper understanding and form your own opinion.

## References

Frank De Jonge on the exact same topic (with more examples in PHP): https://blog.frankdejonge.nl/testing-without-mocking-frameworks/

Martin Fowler on the differences between mocks (option 3) and stubs (option 2): https://martinfowler.com/articles/mocksArentStubs.html

Presentation by Mathias Noback on testing hexagonal architectures: https://matthiasnoback.nl/talk/a-testing-strategy-for-hexagonal-applications/

Some good real life examples on unit tests in php: https://github.com/sarven/unit-testing-tips

A great write up on testing in general: https://dannorth.net/2021/07/26/we-need-to-talk-about-testing/

Quite old (1997!) paper on how **not** to use code coverage: http://www.exampler.com/testing-com/writings/coverage.pdf

Great blog post series on how to avoid mocks: https://philippe.bourgau.net/categories/#how-to-avoid-mocks-series
