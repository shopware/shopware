---
title: Improve extensibility of AdvancedPackageRules
issue: NEXT-15120
---
# Core
* Added `src\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter` abstract class
* Changed `src\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageRules` to extend from `SetGroupScopeFilter`
* Added `src\Core\Checkout\Promotion\Cart\Discount\Filter\PackageFilter` abstract class
* Changed `src\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageFilter` to extend from `PackageFilter`
