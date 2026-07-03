<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * Abstract base for data loader configuration objects.
 *
 * Config objects hold parameters needed by data loaders to fetch data.
 * Each loader type defines its own config structure.
 */
#[Package('framework')]
abstract readonly class AbstractContentDataLoaderConfig implements \JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    abstract public function jsonSerialize(): array;
}
