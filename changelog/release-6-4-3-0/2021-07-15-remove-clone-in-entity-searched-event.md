---
title: remove clone in entity searched event
issue: NEXT-15541
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent::__construct`, to not clone the provided context and criteria.

