---
title: Fix DAL validation for fields with `Required` and `AllowEmptyString` flags
issue: NEXT-26846
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer` and `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer` to add a `NotNull` constraint for the validation if the field has the `Requried` and `AllowEmptyString` flags.
* Changed `\Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CollectionHasSpecifyingExtension` to fix a bug, that lead to wrong phpstan errors.
* Deprecated the `\Shopware\Core\Framework\Api\Converter\ApiVersionConverter`, `\Shopware\Core\Framework\Api\Converter\ConverterRegistry` and `\Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException` as API conversation was not used anymore.
___
# Next Major Version Changes
## Removal of API-Conversion mechanism

The API-Conversion mechanism was not used anymore, therefore, the following classes were removed:
* `\Shopware\Core\Framework\Api\Converter\ApiVersionConverter`
* `\Shopware\Core\Framework\Api\Converter\ConverterRegistry`
* `\Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException`
