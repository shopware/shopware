<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Describes one type a data loader can produce, together with the config template that produces it. The
 * completion residue (keys the caller must still supply) is derived from the loader's config specification.
 */
#[Package('framework')]
final readonly class LoaderTypeCapability
{
    /**
     * @param class-string<Struct> $producedType the actual runtime type (sales-channel class where applicable)
     * @param array<string, mixed> $configTemplate inferable config to produce this type, e.g. ['entity' => 'product']
     * @param list<class-string> $genericParameters informational only (schema display, e.g. EntityCollection<SalesChannelProductEntity>); not used for matching
     */
    public function __construct(
        public string $producedType,
        public array $configTemplate = [],
        public array $genericParameters = [],
    ) {
    }
}
