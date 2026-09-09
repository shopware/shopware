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
     * @param array<string, mixed> $data Raw authoring structure with name/description/icon/layout keys
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(LayoutPresetSpecificationDto $dto): array
    {
        return [
            'name' => $dto->name,
            'description' => $dto->description,
            'icon' => $dto->icon,
            'layout' => $dto->layout,
        ];
    }
}
