<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\LayoutResolution\Cascade;

use Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct\ResolvedData;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class DefaultLayoutStep implements CascadeStepInterface
{
    public function buildFilters(ResolvedData $data, SalesChannelContext $context): array
    {
        return [
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('entityType', null),
                new EqualsFilter('entityId', null),
            ]),
        ];
    }

    public function resolve(EntityCollection $assignments, ResolvedData $data, SalesChannelContext $context): ?string
    {
        /** @var PartialEntity|null $assignment */
        $assignment = $assignments->filter(
            fn (PartialEntity $a) => $a->get('entityType') === null
        )->first();

        if ($assignment && $assignment->get('layoutId')) {
            return $assignment->get('layoutId');
        }

        return null;
    }
}
