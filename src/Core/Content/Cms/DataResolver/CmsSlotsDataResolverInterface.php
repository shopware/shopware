<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

interface CmsSlotsDataResolverInterface
{
    public function resolve(CmsSlotCollection $slots, ResolverContext $resolverContext): CmsSlotCollection;
}
