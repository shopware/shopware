<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContentSystemDataLoaderMap
{
    /**
     * @param array<string, list<LoaderTypeCapability>> $sourceToCapabilities
     * @param array<string, LoaderConfigSpecification> $sourceToConfigSpecifications
     */
    public function __construct(
        public array $sourceToCapabilities,
        public array $sourceToConfigSpecifications,
    ) {
    }

    /**
     * The declared config contract of the given source. Fails hard on an unregistered source rather than
     * returning an empty specification, so a missing declaration surfaces instead of degrading silently.
     */
    public function configSpecificationFor(string $source): LoaderConfigSpecification
    {
        return $this->sourceToConfigSpecifications[$source]
            ?? throw ContentSystemException::dataLoaderNotRegistered($source, 'unknown', 'unknown');
    }

    /**
     * The single derivation of the completion residue: the specification's required keys the caller must still
     * supply given the capability's template, i.e. the required keys minus the keys the template already fills.
     * Order-preserving.
     *
     * @return list<string>
     */
    public function residualConfigKeysFor(string $source, LoaderTypeCapability $capability): array
    {
        $required = $this->configSpecificationFor($source)->requiredKeys();

        return array_values(array_diff($required, array_keys($capability->configTemplate)));
    }

    /**
     * Source identifiers that can produce the given class, subtype-aware: a capability satisfies
     * the requested class when its produced type is the class or a subclass of it.
     *
     * @return list<string>
     */
    public function getSourcesFor(string $className): array
    {
        $sources = [];
        foreach ($this->sourceToCapabilities as $source => $capabilities) {
            foreach ($capabilities as $capability) {
                if (is_a($capability->producedType, $className, true)) {
                    $sources[] = $source;

                    break;
                }
            }
        }

        return $sources;
    }

    /**
     * The first capability of the given source that produces the requested class (subtype-aware),
     * or null when the source cannot produce it.
     */
    public function capabilityFor(string $source, string $className): ?LoaderTypeCapability
    {
        foreach ($this->sourceToCapabilities[$source] ?? [] as $capability) {
            if (is_a($capability->producedType, $className, true)) {
                return $capability;
            }
        }

        return null;
    }
}
