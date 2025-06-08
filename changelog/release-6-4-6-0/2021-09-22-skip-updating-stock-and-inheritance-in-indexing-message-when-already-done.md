---
title: Skip updating stock and inheritance in indexing message when already done
issue: NEXT-17662
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed skip state of `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage` that is sent from `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer::update` to skip stock updates and inheritance updates as this has been done right before sending message
