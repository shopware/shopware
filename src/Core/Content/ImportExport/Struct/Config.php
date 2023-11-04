<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Struct;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateBy;
use Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @phpstan-import-type MappingArray from Mapping
 */
#[Package('system-settings')]
class Config
{
    use JsonSerializableTrait;

    protected MappingCollection $mapping;

    protected UpdateByCollection $updateBy;

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * @param iterable<Mapping|string|MappingArray> $mapping
     * @param iterable<string, mixed> $parameters
     * @param iterable<UpdateBy|string|array<string, mixed>> $updateBy
     */
    public function __construct(
        iterable $mapping,
        iterable $parameters,
        iterable $updateBy
    ) {
        $this->mapping = MappingCollection::fromIterable($mapping);

        foreach ($parameters as $key => $value) {
            $this->parameters[$key] = $value;
        }

        $this->updateBy = UpdateByCollection::fromIterable($updateBy);
    }

    public function getMapping(): MappingCollection
    {
        return $this->mapping;
    }

    public function getUpdateBy(): UpdateByCollection
    {
        return $this->updateBy;
    }

    public function get(string $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    public static function fromLog(ImportExportLogEntity $log): self
    {
        $config = $log->getConfig();

        return new Config(
            $config['mapping'] ?? [],
            $config['parameters'] ?? [],
            $config['updateBy'] ?? []
        );
    }
}
