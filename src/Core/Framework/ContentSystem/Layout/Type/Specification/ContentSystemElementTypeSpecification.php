<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Immutable specification for a content element type.
 *
 * Properties declared here describe what a **hydrated** element of this type looks like
 * in the Store API response — not what is stored in the database. This is the schema
 * for the API output.
 *
 * - FQCN-typed properties (e.g., SalesChannelProductEntity) are filled at runtime by
 *   the hydration pipeline (data loaders or context distribution).
 * - Primitive-typed properties (string, boolean, integer, number) are set statically
 *   at design time and persisted in the element's properties map.
 *
 * The property key is the shared identifier connecting this type spec to element instances.
 * See Layout/Type/README.md for the full key-based linkage documentation.
 *
 * @phpstan-import-type CopilotSchema from CopilotSpecification
 * @phpstan-import-type PropertySchema from PropertySpecification
 * @phpstan-import-type SlotSchema from SlotSpecification
 *
 * @phpstan-type ElementTypeSchema = array{name: string, label: string, description: string, vendor: string, icon: string|null, category: string|null, copilot: CopilotSchema, properties: array<string, PropertySchema>, slots: list<SlotSchema>}
 */
#[Package('framework')]
final readonly class ContentSystemElementTypeSpecification
{
    /**
     * @param array<string, PropertySpecification> $properties
     * @param list<SlotSpecification> $slots
     */
    public function __construct(
        private string $name,
        private string $label,
        private string $description,
        private string $vendor,
        private ?string $icon,
        private ?string $category,
        private CopilotSpecification $copilot,
        private array $properties,
        private array $slots,
        private string $source = '',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return ElementTypeSchema
     */
    public function toSchema(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'vendor' => $this->vendor,
            'icon' => $this->icon,
            'category' => $this->category,
            'copilot' => $this->copilot->toSchema(),
            'properties' => array_map(
                static fn (PropertySpecification $prop) => $prop->toSchema(),
                $this->properties
            ),
            'slots' => array_map(
                static fn (SlotSpecification $slot) => $slot->toSchema(),
                $this->slots
            ),
        ];
    }
}
