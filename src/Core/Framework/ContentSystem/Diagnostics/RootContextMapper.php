<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Maps a bound source's page data requirements to the root-ambient context it supplies to a layout's
 * top-level elements, by resolving each requirement's produced FQCN via its data loader. The
 * page context is exposed exactly as the {@see VirtualRootWrapper} exposes it at runtime: broadcast Single.
 * One mapping path, shared by the entity sources' providedRootContext override and the diagnostics core.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RootContextMapper
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
    ) {
    }

    /**
     * @param list<DataRequirement> $requirements
     *
     * @return list<ProvidedContext>
     */
    public function map(array $requirements): array
    {
        $contexts = [];

        foreach ($requirements as $requirement) {
            $contexts[] = new ProvidedContext(
                contextKey: $requirement->key,
                fqcn: $this->resolveType($requirement),
                contextType: ContextType::Single,
                providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
                distribution: DistributionStrategy::Broadcast,
            );
        }

        return $contexts;
    }

    /**
     * The concrete FQCN a requirement's configured loader produces.
     *
     * @throws ContentSystemException for an unregistered source or an unknown entity; the diagnostics core
     *                                catches the client-defect codes and maps them to an invalid_config violation
     */
    public function resolveType(DataRequirement $requirement): string
    {
        return $this->dataLoaderProvider->get($requirement->source)->resolveProducedType($requirement->config);
    }
}
