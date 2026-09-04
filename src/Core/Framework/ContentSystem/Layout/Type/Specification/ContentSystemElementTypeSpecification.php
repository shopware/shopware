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
 *
 * @phpstan-import-type CopilotSchema from CopilotSpecification
 * @phpstan-import-type PropertySchema from PropertySpecification
 * @phpstan-import-type SlotSchema from SlotSpecification
 *
 * @phpstan-type ElementTypeSchema = array{name: string, label: string, description: string, source: string, icon: string|null, category: string|null, copilot: CopilotSchema, properties: array<string, PropertySchema>, slots: list<SlotSchema>}
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
     * @return array<string, PropertySpecification>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    /**
     * @return list<SlotSpecification>
     */
    public function slots(): array
    {
        return $this->slots;
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
            'source' => $this->source,
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
