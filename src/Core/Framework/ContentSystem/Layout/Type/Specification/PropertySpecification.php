<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type PropertySchema = array{
 *     type: string|list<string>,
 *     translatable: bool,
 *     enum: list<string|int|float|bool>|null,
 *     default: string|int|float|bool|null,
 *     properties: array<string, array<string, mixed>>|null,
 *     required: bool,
 *     mappable: bool,
 *     title: string,
 *     description: string,
 *     adminUI: array<string, mixed>|null
 * }
 */
#[Package('framework')]
final readonly class PropertySpecification
{
    /**
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        private string $name,
        private PropertyType $type,
        private bool $required,
        private string $title,
        private string $description,
        private ?array $adminUI,
        private bool $mappable = false,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function type(): PropertyType
    {
        return $this->type;
    }

    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Whether an author may replace this property's static value with a mapping onto the layout's root
     * entity data. Opt-in per property, because a mapping is a public authoring contract: the property must
     * keep rendering correctly when its value arrives from the entity rather than from the editor.
     */
    public function mappable(): bool
    {
        return $this->mappable;
    }

    /**
     * @return PropertySchema
     */
    public function toSchema(): array
    {
        return [
            ...$this->type->toSchema(),
            'required' => $this->required,
            'mappable' => $this->mappable,
            'title' => $this->title,
            'description' => $this->description,
            'adminUI' => $this->adminUI,
        ];
    }
}
