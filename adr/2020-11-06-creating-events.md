# 2020-11-06 - Creating events in Shopware

## Context

Events throughout Shopware are quite inconsistent.
It is not defined which data it must or can contain.
This mainly depends on the domain where the events are thrown.

## Decision

Developers should always have access to the right context of the current request,
at least the `Shopware\Core\Framework\Context` should be present as property in events.
If the event is thrown in a SalesChannel context,
the `Shopware\Core\System\SalesChannel\SalesChannelContext` should also be present as property.

## Consequences

From now on every new event must implement the `Shopware\Core\Framework\Event\ShopwareEvent` interface.
If a `Shopware\Core\System\SalesChannel\SalesChannelContext` is also available,
the `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` interface must be implemented instead.
