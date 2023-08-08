---
title: Add Domain Exception for customer
issue: NEXT-26919
---
# Core
* Added new static methods for domain exception class `Shopware\Core\Checkout\Customer\CustomerException`.
* Added new static method `addressNotFound` for domain exception class `Shopware\Core\Checkout\Cart\CartException`.
* Added new static method `customerAuthThrottledException` for domain exception class `Shopware\Core\Checkout\Order\OrderException`.
* Added new static method `customerNotFoundByIdException` for domain exception class `Shopware\Core\System\SalesChannel\SalesChannelException`.
* Deprecated the following exceptions in replacement for Domain Exceptions:
    * `Shopware\Core\Checkout\Customer\Exception\CannotDeleteActiveAddressException`
    * `Shopware\Core\Checkout\Customer\Exception\CustomerGroupRegistrationConfigurationNotFound`
    * `Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException`
    * `Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException`
    * `Shopware\Core\Checkout\Customer\Exception\LegacyPasswordEncoderNotFoundException`
    * `Shopware\Core\Checkout\Customer\Exception\WishlistProductNotFoundException`
    * `Shopware\Core\Checkout\Customer\Exception\NoHashProvidedException`
