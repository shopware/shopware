# 6.7.0.0

## Introduced in 6.6.0.0

## Replace `isEmailUsed` with `isEmailAlreadyInUse`:
* Replace `isEmailUsed` with `isEmailAlreadyInUse` in `sw-users-permission-user-detail`.

## AccountService refactoring

The `Shopware\Core\Checkout\Customer\SalesChannel\AccountService::login` method is removed. Use `AccountService::loginByCredentials` or `AccountService::loginById` instead.

Unused constant `Shopware\Core\Checkout\Customer\CustomerException::CUSTOMER_IS_INACTIVE` and unused method `Shopware\Core\Checkout\Customer\CustomerException::inactiveCustomer` are removed.
## Deprecated comparison methods:
* `floatMatch` and `arrayMatch` methods in `src/Core/Framework/Rule/CustomFieldRule.php` will be removed for Shopware 6.7.0.0

## Introduced in 6.5.7.0
## New `technicalName` property for payment and shipping methods
The `technicalName` property will be required for payment and shipping methods in the API.
The `technical_name` column will be made non-nullable for the `payment_method` and `shipping_method` tables in the database.

Plugin developers will be required to supply a `technicalName` for their payment and shipping methods.

Merchants must review their custom created payment and shipping methods for the new `technicalName` property and update their methods through the administration accordingly.
