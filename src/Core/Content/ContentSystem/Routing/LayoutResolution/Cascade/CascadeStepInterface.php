<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\LayoutResolution\Cascade;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutAssignmentEntity;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct\ResolvedData;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Cascade step using polymorphism instead of conditionals.
 *
 * @internal
 */
#[Package('discovery')]
interface CascadeStepInterface
{
    /**
     * @return array<MultiFilter>
     */
    public function buildFilters(ResolvedData $data, SalesChannelContext $context): array;

    /**
     * @param EntityCollection<ContentLayoutAssignmentEntity> $assignments
     */
    public function resolve(EntityCollection $assignments, ResolvedData $data, SalesChannelContext $context): ?string;
}
