<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContentSystemDataLoaderTypeMap
{
    /**
     * @var array<string, list<string>>
     */
    private array $classToSources;

    /**
     * @param array<string, list<ContentSystemDataLoaderTypeDescriptor>> $sourceToTypes
     */
    public function __construct(
        public array $sourceToTypes,
    ) {
        $classToSources = [];
        foreach ($sourceToTypes as $source => $descriptors) {
            foreach ($descriptors as $descriptor) {
                $classToSources[$descriptor->className][] = $source;
            }
        }

        $this->classToSources = $classToSources;
    }

    /**
     * @return list<string> Source identifiers that can provide the given class.
     */
    public function getSourcesFor(string $className): array
    {
        return $this->classToSources[$className] ?? [];
    }
}
