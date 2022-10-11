---
title: Update BC checker
issue: NEXT-19739
---
# Core
* Changed version from roave/backward-compatibility-check from 5.x to latest 7.x, the BC checker requires now at least PHP 8.0.
* Deprecated class `\Shopware\Core\Framework\Event\FlowEvent`, it will be removed in v6.5.0, use `\Shopware\Core\Content\Flow\Dispatching\StorableFlow` instead.
* Deprecated properties `$config` and `$connection` in `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter`, they will be private in v6.5.0..
* Deprecated properties `$restoredContext` and `$currentContext` in `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent`, they will be private in v6.5.0..
* Deprecated properties `$csrfEnabled` and `$csrfMode` in `\Shopware\Storefront\Framework\Csrf\CsrfRouteListener`, they will be private in v6.5.0..
___
# Next Major Version Changes
## Removal of `\Shopware\Core\Framework\Event\FlowEvent`
We removed the `\Shopware\Core\Framework\Event\FlowEvent`, as Flow Actions are not executed over symfonys event system anymore.
Implement the `handleFlow()` method in your `FlowAction` and tag your actions as `flow.action` instead.
