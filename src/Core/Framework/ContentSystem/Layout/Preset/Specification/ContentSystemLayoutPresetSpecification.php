<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContentSystemLayoutPresetSpecification
{
    /**
     * @param list<array<string, mixed>> $payload
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $icon,
        public array $payload,
        public ?string $category = null,
        public int $priority = 0,
        public ?string $illustration = null,
        public ?string $nameSnippet = null,
        public ?string $descriptionSnippet = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            ...($this->nameSnippet !== null ? ['nameSnippet' => $this->nameSnippet] : []),
            ...($this->descriptionSnippet !== null ? ['descriptionSnippet' => $this->descriptionSnippet] : []),
            'description' => $this->description,
            'icon' => $this->icon,
            'illustration' => $this->illustration,
            'category' => $this->category,
            'priority' => $this->priority,
            'payload' => $this->payload,
        ];
    }
}
