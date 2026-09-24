<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-type catalog cut down to what an agent needs to author a layout: no admin UI hints, no long texts.
 *
 * @phpstan-import-type ElementTypeSchema from ContentSystemElementTypeSpecification
 * @phpstan-import-type PropertySchema from PropertySpecification
 * @phpstan-import-type SlotSchema from SlotSpecification
 *
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
final class ElementTypeCatalogProjector
{
    private const DEFAULT_LENGTH = 80;

    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly AbstractContentSystemLayoutPresetRegistry $presetRegistry,
    ) {
    }

    /**
     * @return array{types: list<array<string, mixed>>, presets: list<array{id: string, name: string, description: string|null}>}
     */
    public function project(): array
    {
        $types = [];
        foreach ($this->typeRegistry->all() as $type) {
            $types[] = $this->type($type->toSchema());
        }

        $presets = array_map(
            static fn (ContentSystemLayoutPresetSpecification $preset): array => [
                'id' => $preset->id,
                'name' => $preset->name,
                'description' => $preset->description,
            ],
            array_values($this->presetRegistry->all()),
        );

        return ['types' => $types, 'presets' => $presets];
    }

    /**
     * @param ElementTypeSchema $schema
     *
     * @return array<string, mixed>
     */
    private function type(array $schema): array
    {
        $type = [
            'name' => $schema['name'],
            'label' => $schema['label'],
            'summary' => $schema['copilot']['summary'] !== '' ? $schema['copilot']['summary'] : $schema['description'],
        ];

        if ($schema['copilot']['hints'] !== []) {
            $type['hints'] = $schema['copilot']['hints'];
        }

        $type['properties'] = array_map($this->property(...), $schema['properties']);

        if ($schema['slots'] !== []) {
            $type['slots'] = array_map($this->slot(...), $schema['slots']);
        }

        return $type;
    }

    /**
     * @param PropertySchema $schema
     *
     * @return array<string, mixed>
     */
    private function property(array $schema): array
    {
        $property = ['type' => $schema['type'], 'required' => $schema['required']];

        $default = $schema['default'];
        if (\is_string($default) && mb_strlen($default) > self::DEFAULT_LENGTH) {
            $default = mb_substr($default, 0, self::DEFAULT_LENGTH) . '…';
        }

        if ($default !== null) {
            $property['default'] = $default;
        }

        if ($schema['enum'] !== null) {
            $property['enum'] = $schema['enum'];
        }

        return $property;
    }

    /**
     * @param SlotSchema $schema
     *
     * @return array<string, mixed>
     */
    private function slot(array $schema): array
    {
        $slot = ['name' => $schema['name']];

        if ($schema['maxElements'] !== null) {
            $slot['maxElements'] = $schema['maxElements'];
        }

        if ($schema['allowList'] !== []) {
            $slot['allowList'] = $schema['allowList'];
        }

        return $slot;
    }
}
