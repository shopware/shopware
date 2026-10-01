---
title: Fix plugin config default values
issue: #12349
---
# Core
* Changed `\Shopware\Core\System\SystemConfig\Util\ConfigReader` to first `phpize` and then parse the default values for input fields according to the type of the field.
* Changed `\Shopware\Core\System\SystemConfig\SystemConfigService` to not `phpize` the default values anymore, as they are already parsed by the ConfigReader.
* Deprecated `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`, `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`, and `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`, use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.
* Deprecated `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()`, the tracing is not needed anymore since the cache rework for 6.7.0.0. For now the methods are still available, but they do nothing.
___
# Upgrade Information

## Plugin config default values
The default values for plugin config fields are now parsed according to the type of the field.
This means default values for `checkbox` and `bool` fields are parsed as boolean values, `int` fields are parsed as integer values, and `float` fields are parsed as float values.
Everything else is parsed as string values. With this the default values are now consistent based on the type of the field and the type does not depend on the actual value.
This makes it more consistent as otherwise the types could change when they are configured in the Administration.

## Deprecated SystemConfig exceptions
The exceptions 
* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`, 
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`, and 
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`
are now deprecated and will be removed in v6.8.0.0.
Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

## Deprecated SystemConfigService tracing methods
The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` are deprecated and will be removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0. For now the methods are still available, but they do nothing.
___
# Next Major Version Changes

## Removed SystemConfig exceptions
The exceptions
* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`,
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`, and
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`
were removed.
Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

## Deprecated SystemConfigService tracing methods
The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` were removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0.