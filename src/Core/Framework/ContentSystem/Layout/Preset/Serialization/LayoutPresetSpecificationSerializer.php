<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutPresetSpecificationSerializer
{
    /**
     * @param array<string, mixed> $data Raw authoring structure with name/description/icon/illustration/category/priority/layout keys
     */
    public function denormalize(array $data): LayoutPresetSpecificationDto
    {
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $icon = $data['icon'] ?? null;
        /** @var list<array<string, mixed>> $layout */
        $layout = $data['layout'] ?? null;

        return new LayoutPresetSpecificationDto(
            name: \is_string($name) ? $name : '',
            description: \is_string($description) ? $description : '',
            icon: \is_string($icon) ? $icon : '',
            layout: \is_array($layout) ? $layout : [],
            category: $data['category'] ?? null,
            priority: \array_key_exists('priority', $data) ? $data['priority'] : 0,
            illustration: $data['illustration'] ?? null,
            nameSnippet: $data['nameSnippet'] ?? null,
            descriptionSnippet: $data['descriptionSnippet'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(LayoutPresetSpecificationDto $dto): array
    {
        $data = [
            'name' => $dto->name,
            'description' => $dto->description,
            'icon' => $dto->icon,
            'layout' => $dto->layout,
        ];

        if ($dto->nameSnippet !== null) {
            $data['nameSnippet'] = $dto->nameSnippet;
        }
        if ($dto->descriptionSnippet !== null) {
            $data['descriptionSnippet'] = $dto->descriptionSnippet;
        }
        if ($dto->illustration !== null) {
            $data['illustration'] = $dto->illustration;
        }
        if ($dto->category !== null) {
            $data['category'] = $dto->category;
        }
        if ($dto->priority !== 0) {
            $data['priority'] = $dto->priority;
        }

        return $data;
    }
}
