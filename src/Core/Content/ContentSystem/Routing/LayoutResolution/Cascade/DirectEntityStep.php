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
final readonly class DirectEntityStep implements CascadeStepInterface
{
    public function __construct(
        private string $entityType
    ) {
    }

    public function buildFilters(ResolvedData $data, SalesChannelContext $context): array
    {
        $entityId = $this->getEntityId($data);

        if ($entityId === null) {
            return [];
        }

        return [
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('entityType', $this->entityType),
                new EqualsFilter('entityId', $entityId),
            ]),
        ];
    }

    public function resolve(EntityCollection $assignments, ResolvedData $data, SalesChannelContext $context): ?string
    {
        $entityId = $this->getEntityId($data);

        if ($entityId === null) {
            return null;
        }

        /** @var PartialEntity|null $assignment */
        $assignment = $assignments->filter(
            fn (PartialEntity $a) => $a->get('entityType') === $this->entityType
                && $a->get('entityId') === $entityId
        )->first();

        if ($assignment && $assignment->get('layoutId')) {
            return $assignment->get('layoutId');
        }

        return null;
    }

    private function getEntityId(ResolvedData $data): ?string
    {
        return $data->getEntityId($this->entityType . '_id')
            ?? $data->getEntityId($this->entityType);
    }
}
