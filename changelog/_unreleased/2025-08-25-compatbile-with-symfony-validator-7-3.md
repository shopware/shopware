---
title: Compatbile with symfony/validator 7.3
issue: #11872
---
# Core
* Changed these method to use named argument in constraints compatible with symfony/validator~7.3
    - `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer::getConstraints`
    - `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer::getConstraints`
    - `\Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsFieldSerializer::getConstraints`
    - `\Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer::getConstraints`
    - `\Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer::getConstraints`
* Deprecated `$options` arguments in Shopware's custom validator constraints to use named argument instead
* Deprecated `\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode::$countryId` and `$caseSensitiveCheck` public properties, will change to protect to match other constraint in the next major
* Added `#[HasNamedArguments]` for each custom validator constraints to be matched with symfony's standard constraint  
* Added arguments for custom validator constraints to explicitly show what arguments are required for the constraints instead of passing it to `$options` argument  
___
# Upgrade Information
## Use named argument when defining constraints

Previously when using constraints, you can pass an array of options into the constraint's constructor, but it hide the dependency arguments of the constraint as well as validating input arguments.
Similar with standard Symfony constraints from `symfony/validator~7.3`, we converted from array to named argument syntax

```php
// Before:
new CustomerEmailUnique(['salesChannelContext' => $context])
```
to

```php
new CustomerEmailUnique(salesChannelContext: $context)
```

# Next Major Version Changes
## Removal of `$options` parameter in custom validator's constraints

The `$options` of all Shopware's custom validator constraint are removed, if you use one of them, please use named argument instead

```php
// Before:
new CustomerEmailUnique(['salesChannelContext' => $context])
```
to

```php
new CustomerEmailUnique(salesChannelContext: $context)
```

Affected constraints are:

```
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode
\Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists
\Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists
```