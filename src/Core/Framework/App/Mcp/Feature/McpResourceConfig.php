<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Feature;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 *
 * @phpstan-type McpResourcePayload array{name: string, uri: string, url: string, mimeType?: string|null, label?: array<string, string>, description?: array<string, string>}
 */
#[Package('framework')]
readonly class McpResourceConfig implements AppFeatureConfig
{
    public function __construct(
        public string $name,
        public string $uri,
        public string $url,
        public ?string $mimeType,
        public TranslatedString $label,
        public TranslatedString $description,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return McpResourcePayload
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'uri' => $this->uri,
            'url' => $this->url,
            'mimeType' => $this->mimeType,
            'label' => $this->label->all(),
            'description' => $this->description->all(),
        ];
    }
}
