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
 * @phpstan-type McpPromptPayload array{name: string, url: string, label?: array<string, string>, description?: array<string, string>}
 */
#[Package('framework')]
readonly class McpPromptConfig implements AppFeatureConfig
{
    public function __construct(
        public string $name,
        public string $url,
        public TranslatedString $label,
        public TranslatedString $description,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return McpPromptPayload
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'label' => $this->label->all(),
            'description' => $this->description->all(),
        ];
    }
}
