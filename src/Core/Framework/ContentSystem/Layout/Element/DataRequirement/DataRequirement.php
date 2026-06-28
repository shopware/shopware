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
     * @return array{key: string, source: string, config: array<string, mixed>|\stdClass}
     */
    public function jsonSerialize(): array
    {
        $config = $this->config->jsonSerialize();

        return [
            'key' => $this->key,
            'source' => $this->source,
            // The wire type is an object; an empty config must encode as {}, not []
            'config' => $config === [] ? new \stdClass() : $config,
        ];
    }
}
