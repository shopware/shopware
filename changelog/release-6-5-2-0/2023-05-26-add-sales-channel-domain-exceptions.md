---
title: Add Sales channel domain exceptions
issue: NEXT-27207
---
# Core
* Added a new domain exception in `\Shopware\Core\System\SalesChannel\SalesChannelException`
* Changed `\Shopware\Core\System\SalesChannel\Context\BaseContextFactory::create` to apply domain exception instead of throw a \RuntimeException
