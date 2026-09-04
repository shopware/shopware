<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\Log\Package;

/**
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
