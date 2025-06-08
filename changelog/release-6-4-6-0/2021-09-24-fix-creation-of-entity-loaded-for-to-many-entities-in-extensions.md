---
title: Fix Creation of EntityLoadedEvents for toManyEntities in extensions
issue: NEXT-17328
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory` to also create EntityLoadedEvents for toMany-Associations inside extensions.
