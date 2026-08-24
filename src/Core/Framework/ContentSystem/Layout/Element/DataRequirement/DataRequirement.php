<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class DataRequirement implements \JsonSerializable
{
    public function __construct(
        public string $key,
        public string $source,
        public AbstractContentDataLoaderConfig $config
    ) {
    }

    /**
     * An empty `config` map is emitted as `[]`, consistent with every content-element read path and the DAL
     * write/validation/storage form.
     *
     * @return array{key: string, source: string, config: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'source' => $this->source,
            'config' => $this->config->jsonSerialize(),
        ];
    }
}
