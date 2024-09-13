<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;

/**
 * @internal We might break this in v6.2
 */
#[Package('services-settings')]
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
                throw new \InvalidArgumentException(\sprintf(
                    'Supported entities should be collection of strings. %s given.',
                    \gettype($entityName)
                ));
            }
            $this->entities[] = $entityName;
        }

        foreach ($fileTypes as $fileType) {
            if (!\is_string($fileType)) {
                throw new \InvalidArgumentException(\sprintf(
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
        $twoGiB = 2 * 1024 * 1024 * 1024; // 2 GiB as fallback, because file size is stored in MySQL INT column

        return MemorySizeCalculator::getMaxUploadSize($twoGiB);
    }
}
