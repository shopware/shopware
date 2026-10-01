<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class LayoutReference
{
    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(
        public string $id,
        public string $name,
        public ?string $version,
        public array $settings,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function create(string $id, string $name, ?string $version, array $settings = []): self
    {
        return new self($id, $name, $version, $settings);
    }

    public static function fromEntity(ContentLayoutEntity $entity): self
    {
        return self::create($entity->getId(), $entity->getName(), $entity->getVersion(), $entity->getSettings() ?? []);
    }
}
