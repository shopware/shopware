<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;

interface SlotTypeDataResolverInterface
{
    public function getType(): string;

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection;

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, SlotDataResolveResult $result): void;
}
