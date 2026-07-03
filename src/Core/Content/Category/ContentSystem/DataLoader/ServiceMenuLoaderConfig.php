<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ServiceMenuLoaderConfigData array{
 *   rootId?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class ServiceMenuLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $rootId Navigation root ID or alias (default: service-navigation)
     */
    public function __construct(
        public ?string $rootId = null,
    ) {
    }

    /**
     * @return ServiceMenuLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->rootId !== null) {
            $data['rootId'] = $this->rootId;
        }

        return $data;
    }
}
