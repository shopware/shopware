<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Request-time source selection for the diagnose route: entity types via the existing supportsEntityType()
 * selection over the tagged entity sources, header/footer sections via a ContentSection-keyed locator that
 * Storefront contributes to. Returns the source instance on which the route calls providedRootContext().
 *
 * @internal
 */
#[Package('framework')]
class SpecificationSourceResolver
{
    /**
     * @param iterable<AbstractSpecificationSource> $entitySources
     * @param ServiceLocator<AbstractSpecificationSource> $sectionSources
     */
    public function __construct(
        private readonly iterable $entitySources,
        private readonly ServiceLocator $sectionSources,
    ) {
    }

    public function resolveByEntityType(string $entityType): AbstractSpecificationSource
    {
        foreach ($this->entitySources as $source) {
            if ($source->supportsEntityType($entityType)) {
                return $source;
            }
        }

        throw ContentSystemException::unknownEntityType($entityType);
    }

    public function resolveBySection(ContentSection $section): AbstractSpecificationSource
    {
        if (!$this->sectionSources->has($section->value)) {
            throw ContentSystemException::noSourceForSection($section->value);
        }

        return $this->sectionSources->get($section->value);
    }
}
