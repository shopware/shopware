<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContentSystemDataLoaderTypeMap
{
    /**
     * @param array<string, list<LoaderTypeCapability>> $sourceToCapabilities
     */
    public function __construct(
        public array $sourceToCapabilities,
    ) {
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
