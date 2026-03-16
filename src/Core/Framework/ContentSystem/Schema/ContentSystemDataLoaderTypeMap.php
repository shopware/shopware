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
     * @param array<string, list<ContentSystemDataLoaderTypeDescriptor>> $sourceToTypes
     */
    public function __construct(
        public array $sourceToTypes,
    ) {
    }

    /**
     * @return list<string> Source identifiers that can provide the given class.
     */
    public function getSourcesFor(string $className): array
    {
        $sources = [];
        foreach ($this->sourceToTypes as $source => $descriptors) {
            foreach ($descriptors as $descriptor) {
                if ($descriptor->className === $className) {
                    $sources[] = $source;
                }
            }
        }

        return $sources;
    }
}
