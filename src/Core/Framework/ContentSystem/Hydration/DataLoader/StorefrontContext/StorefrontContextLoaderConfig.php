<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class StorefrontContextLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function jsonSerialize(): array
    {
        return [];
    }
}
