---
title: Fix error handling for message handlers
issue: NEXT-33079
---
# Core
* Changed `\Shopware\Core\Framework\Log\Monolog\ErrorCodeLogLevelHandler` to adjust log level, based on the inner exception for `HandlerFailedException` as symfony wraps all exception thrown in message handlers.
