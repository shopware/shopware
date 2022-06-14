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
Here are some good examples of unit tests:
- [CriteriaTest](https://github.com/shopware/platform/blob/trunk/src/Core/Framework/Test/DataAbstractionLayer/Search/CriteriaTest.php)
  - Good example for simple DTO tests
- [CashRounding](https://github.com/shopware/platform/blob/trunk/src/Core/Checkout/Test/Cart/Price/CashRoundingTest.php)
  - Nice test matrix for single service coverage
- [AddCustomerTagActionTest](https://github.com/shopware/platform/blob/trunk/src/Core/Content/Test/Flow/Dispatching/Action/AddCustomerTagActionTest.php)
  - A good example of how to test flow actions and use mocks for repositories

Here are some good examples of integration tests:
- [ProductCartTest](https://github.com/shopware/platform/blob/trunk/src/Core/Content/Test/Product/Cart/ProductCartTest.php)
  - Slim product cart test with good helper function integrations
- [CachedProductListingRouteTest](https://github.com/shopware/platform/blob/trunk/src/Core/Content/Test/Product/SalesChannel/Listing/CachedProductListingRouteTest.php)
  - This test is a little complex, but has a very good test case matrix with good descriptions and reusable test code.