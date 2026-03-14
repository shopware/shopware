<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type PropertySchema = array{type: string, translatable: bool, enum: list<string|int|float|bool>|null, default: string|int|float|bool|null, required: bool, title: string, description: string, adminUI: array<string, mixed>|null}
 */
#[Package('framework')]
final readonly class PropertySpecification
{
    /**
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        private string $name, // @phpstan-ignore property.onlyWritten (identity field, will be used by future domain API)
        private PropertyType $type,
        private bool $required,
        private string $title,
        private string $description,
        private ?array $adminUI,
    ) {
    }

    /**
     * @return PropertySchema
     */
    public function toSchema(): array
    {
        return [
            ...$this->type->toSchema(),
            'required' => $this->required,
            'title' => $this->title,
            'description' => $this->description,
            'adminUI' => $this->adminUI,
        ];
    }
}
