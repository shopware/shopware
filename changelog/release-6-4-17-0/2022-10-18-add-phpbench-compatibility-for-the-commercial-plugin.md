---
title: Add PHPBench compatibility for the Commercial plugin
issue: NEXT-17583
---
# Core
* Added additional pre-generated UUID data to the `FixtureLoader.php` fixture generator
* Added `createProducts` and `createCustomer` methods in `BasicTestDataBehaviour` which utilise the `CustomerBuilder` and `ProductBuilder` fixture helpers
___
# Tests
* Added 100 products to phpbench `data.json` to allow for improved load testing in bench tests
* Added phpbench tests for `ProductListingRoute.php` and `ProductDetailRoute.php`
* Adjusted phpbench bootstrapping (initial fixture creation) to allow the loading of custom fixtures from the commercial plugin (conditionally based upon the phpbench `--group` flag)
* Allow `Fixtures.php` to load fixtures with only string input
___
