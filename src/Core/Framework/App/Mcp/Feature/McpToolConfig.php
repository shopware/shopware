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
 * @phpstan-type McpToolPayload array{name: string, url: string, requiredPrivileges?: list<string>, inputSchema?: array<string, array{type: string, description?: string, required?: bool}>|null, label?: array<string, string>, description?: array<string, string>}
 */
#[Package('framework')]
readonly class McpToolConfig implements AppFeatureConfig
{
    /**
     * @param list<string> $requiredPrivileges
     * @param array<string, array{type: string, description?: string, required?: bool}>|null $inputSchema
     */
    public function __construct(
        public string $name,
        public string $url,
        public array $requiredPrivileges,
        public ?array $inputSchema,
        public TranslatedString $label,
        public TranslatedString $description,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return McpToolPayload
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'requiredPrivileges' => $this->requiredPrivileges,
            'inputSchema' => $this->inputSchema,
            'label' => $this->label->all(),
            'description' => $this->description->all(),
        ];
    }
}
