<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal We might break this in v6.2
 */
#[Package('system-settings')]
class SupportedFeaturesService
{
    /**
     * @var list<string>
     */
    private array $entities = [];

    /**
     * @var list<string>
     */
    private array $fileTypes = [];

    /**
     * @param iterable<string> $entities
     * @param iterable<string> $fileTypes
     */
    public function __construct(
        iterable $entities,
        iterable $fileTypes
    ) {
        foreach ($entities as $entityName) {
            if (!\is_string($entityName)) {
                throw new \InvalidArgumentException(sprintf(
                    'Supported entities should be collection of strings. %s given.',
                    \gettype($entityName)
                ));
            }
            $this->entities[] = $entityName;
        }

        foreach ($fileTypes as $fileType) {
            if (!\is_string($fileType)) {
                throw new \InvalidArgumentException(sprintf(
                    'Supported file types should be collection of strings. %s given',
                    \gettype($fileType)
                ));
            }
            $this->fileTypes[] = $fileType;
        }
    }

    /**
     * @return list<string>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @return list<string>
     */
    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    public function getUploadFileSizeLimit(): int
    {
        $twoGiB = 2 * 1024 * 1024 * 1024;
        $values = [
            self::toBytes((string) \ini_get('upload_max_filesize')),
            self::toBytes((string) \ini_get('post_max_size')),
            $twoGiB, // 2 GiB as fallback, because file size is stored in MySQL INT column
        ];

        $limits = array_filter($values, static fn (int $value) => $value > 0);

        return min($limits);
    }

    private static function toBytes(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        $length = mb_strlen($value);
        $qty = (int) mb_substr($value, 0, $length - 1);
        $unit = mb_strtolower(mb_substr($value, $length - 1));
        match ($unit) {
            'k' => $qty *= 1024,
            'm' => $qty *= 1048576,
            'g' => $qty *= 1073741824,
            default => $qty,
        };

        return $qty;
    }
}
