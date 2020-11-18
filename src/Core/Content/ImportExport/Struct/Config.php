<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Struct;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

class Config
{
    use JsonSerializableTrait;

    /**
     * @var MappingCollection
     */
    protected $mapping;

    /**
     * @var array
     */
    protected $parameters = [];

    public function __construct(iterable $mapping, iterable $parameters)
    {
        $this->mapping = MappingCollection::fromIterable($mapping);

        foreach ($parameters as $key => $value) {
            $this->parameters[$key] = $value;
        }
    }

    public function getMapping(): MappingCollection
    {
        return $this->mapping;
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
            $config['parameters'] ?? []
        );
    }
}
