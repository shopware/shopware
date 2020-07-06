---
title: Fix fetching version-aware ManyToOne relations
issue: NEXT-12749
author: Joshua Behrens
author_email: behrens@heptacom.de
author_github: @JoshuaBehrens
---
# Core
* Fixed bug on SQL generation in `Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\ManyToOneJoinBuilder` when fetching version-aware ManyToOne relations like `product_translation.product`
