---
title: Make PartialEntity and Criteria fields public API
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31262
---
# Core
* Removed `internal` PHP docs from `\Shopware\Core\Framework\DataAbstractionLayer\PartialEntity`, `\Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent`, `\Shopware\Core\System\SalesChannel\Entity\PartialSalesChannelEntityLoadedEvent`, `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addFields` and `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::getFields`
