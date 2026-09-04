<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\Log\Package;

/**
 * A ready-made layout fragment the admin can drop into the layout it is editing. The payload is a complete or
 * partial layout tree (a list of encoded elements, same wire shape as a saved layout); name, description and icon
 * are the display metadata the admin shows in the preset picker. The id is author-provided and must be unique so
 * a preset can be identified definitively.
 *
 * @internal
 *
 * @phpstan-type PresetPayload list<array<string, mixed>>
 */
#[Package('framework')]
final readonly class LayoutPreset
{
    /**
     * @param PresetPayload $payload
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $icon,
        public array $payload,
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
            'description' => $this->description,
            'icon' => $this->icon,
            'payload' => $this->payload,
        ];
    }
}
