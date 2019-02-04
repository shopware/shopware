<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Routing\InternalRequest;

interface SlotTypeDataResolverInterface
{
    public function getType(): string;

    public function collect(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context): ?CriteriaCollection;

    public function enrich(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context, SlotDataResolveResult $result): CmsSlotEntity;
}
