<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Struct\Struct;

class UpdateBy extends Struct
{
    protected string $entityName;

    protected ?string $mappedKey;

    public function __construct(string $entityName, ?string $mappedKey = null)
    {
        $this->entityName = $entityName;
        $this->mappedKey = $mappedKey;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getMappedKey(): ?string
    {
        return $this->mappedKey;
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['entityName'])) {
            throw new \InvalidArgumentException('entityName is required in mapping');
        }

        $mapping = new self($data['entityName']);
        $mapping->assign($data);

        return $mapping;
    }
}
