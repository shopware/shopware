---
title: Deprecate LoggerFactory
issue: NEXT-30950
---
# Core
* Deprecated `\Shopware\Core\Framework\Log\LoggerFactory` as it doesn't work very well and can be replaced with monolog configuration
___
# Next Major Version Changes

## \Shopware\Core\Framework\Log\LoggerFactory:
`\Shopware\Core\Framework\Log\LoggerFactory` will be removed. You can use monolog configuration to achieve the same results. See https://symfony.com/doc/current/logging/channels_handlers.html.

