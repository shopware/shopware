<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Struct;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

class Config
{
    use JsonSerializableTrait;

    protected MappingCollection $mapping;

    protected UpdateByCollection $updateBy;

    protected array $parameters = [];

    /**
     * @deprecated tag:v6.5.0 The parameter $updateBy will be required
     */
    public function __construct(iterable $mapping, iterable $parameters, iterable $updateBy = [])
    {
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

    public function get(string $key)
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
