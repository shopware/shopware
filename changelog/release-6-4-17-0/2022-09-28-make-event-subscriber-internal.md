---
title: Make EventSubscribers internal
issue: NEXT-22389
---
# Core
* Deprecated all EventSubscribers, as they will internal from 6.5.0 onward, don't call the EventSubscribers directly.
* Deprecated class `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry` and it's method `getSubscribedEvents()` as the registry won't implement the `EventSubscriberInterface` in 6.5.0 anymore.
