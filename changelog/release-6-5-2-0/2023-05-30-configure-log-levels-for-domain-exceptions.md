---
title: Allow configuration for log level on error codes for domain exceptions
issue: NEXT-27176
---
# Core
* Added `\Shopware\Core\Framework\Log\Monolog\ErrorCodeLogLevelHandler` to configure the log level of shopware exception based on the error code.
___
# Upgrade information
## Configurable log levels for exceptions by error code

The `shopware.logger.error_code_log_levels` config option was added to allow to configure different log levels based on the error code of the exception.
You can use that option as follows in your shopware.yaml:
```yaml
shopware:
  logger:
    error_code_log_levels:
      PRODUCT__CATEGORY_NOT_FOUND: notice
      ...
```
