<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\SlotSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ElementTypeSpecificationSerializer
{
    /**
     * @param array<string, mixed> $data Raw YAML/DB structure with meta/properties/slots top-level keys
     */
    public function denormalize(array $data): ElementTypeSpecificationDto
    {
        $meta = $data['meta'] ?? [];
        $description = $meta['description'] ?? '';

        $copilotData = $meta['copilot'] ?? [];
        $copilot = new CopilotSpecificationDto(
            summary: $copilotData['summary'] ?? $description,
            hints: $copilotData['hints'] ?? [],
        );

        $properties = [];
        foreach ($data['properties'] ?? [] as $propertyName => $propertyData) {
            $properties[$propertyName] = new PropertySpecificationDto(
                name: (string) $propertyName,
                type: $propertyData['type'] ?? '',
                required: $propertyData['required'] ?? false,
                translatable: $propertyData['translatable'] ?? false,
                title: $propertyData['title'] ?? '',
                description: $propertyData['description'] ?? '',
                enum: $propertyData['enum'] ?? null,
                default: $propertyData['default'] ?? null,
                adminUI: $propertyData['adminUI'] ?? null,
            );
        }

        $slots = [];
        foreach ($data['slots'] ?? [] as $slotData) {
            $slots[] = new SlotSpecificationDto(
                name: $slotData['name'] ?? '',
                maxElements: $slotData['maxElements'] ?? null,
                allowList: $slotData['allowList'] ?? [],
                description: $slotData['description'] ?? '',
            );
        }

        return new ElementTypeSpecificationDto(
            label: $meta['label'] ?? '',
            description: $description,
            icon: $meta['icon'] ?? null,
            category: $meta['category'] ?? null,
            copilot: $copilot,
            properties: $properties,
            slots: $slots,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(ElementTypeSpecificationDto $dto): array
    {
        $meta = [
            'label' => $dto->label,
            'description' => $dto->description,
        ];

        if ($dto->icon !== null) {
            $meta['icon'] = $dto->icon;
        }
        if ($dto->category !== null) {
            $meta['category'] = $dto->category;
        }

        if ($dto->copilot->summary !== '' || $dto->copilot->hints !== []) {
            $copilot = [];
            if ($dto->copilot->summary !== '') {
                $copilot['summary'] = $dto->copilot->summary;
            }
            if ($dto->copilot->hints !== []) {
                $copilot['hints'] = $dto->copilot->hints;
            }
            $meta['copilot'] = $copilot;
        }

        $result = ['meta' => $meta];

        if ($dto->properties !== []) {
            $properties = [];
            foreach ($dto->properties as $key => $prop) {
                $propData = ['type' => $prop->type];
                if ($prop->required) {
                    $propData['required'] = true;
                }
                if ($prop->translatable) {
                    $propData['translatable'] = true;
                }
                if ($prop->title !== '') {
                    $propData['title'] = $prop->title;
                }
                if ($prop->description !== '') {
                    $propData['description'] = $prop->description;
                }
                if ($prop->enum !== null) {
                    $propData['enum'] = $prop->enum;
                }
                if ($prop->default !== null) {
                    $propData['default'] = $prop->default;
                }
                if ($prop->adminUI !== null) {
                    $propData['adminUI'] = $prop->adminUI;
                }
                $properties[$key] = $propData;
            }
            $result['properties'] = $properties;
        }

        if ($dto->slots !== []) {
            $slots = [];
            foreach ($dto->slots as $slot) {
                $slotData = ['name' => $slot->name];
                if ($slot->maxElements !== null) {
                    $slotData['maxElements'] = $slot->maxElements;
                }
                if ($slot->allowList !== []) {
                    $slotData['allowList'] = $slot->allowList;
                }
                if ($slot->description !== '') {
                    $slotData['description'] = $slot->description;
                }
                $slots[] = $slotData;
            }
            $result['slots'] = $slots;
        }

        return $result;
    }
}
