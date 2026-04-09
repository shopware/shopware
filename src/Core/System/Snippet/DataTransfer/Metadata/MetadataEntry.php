<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\DataTransfer\Metadata;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 */
#[Package('discovery')]
class MetadataEntry
{
    use JsonSerializableTrait;

    private function __construct(
        public readonly string $locale,
        public readonly \DateTime $updatedAt,
        public readonly int $progress,
        public bool $isUpdateRequired = false,
    ) {
    }

    /**
     * @param array{locale: string, updatedAt: string, progress: int} $data
     */
    public static function create(array $data): self
    {
        return new self(
            locale: $data['locale'],
            updatedAt: new \DateTime($data['updatedAt']),
            progress: $data['progress'],
        );
    }

    public function markForUpdate(): void
    {
        $this->isUpdateRequired = true;
    }
}
