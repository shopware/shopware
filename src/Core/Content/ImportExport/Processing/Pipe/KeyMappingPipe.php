<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Util\ArrayNormalizer;

class KeyMappingPipe extends AbstractPipe
{
    /**
     * @var MappingCollection
     */
    private $mapping;

    /**
     * @var bool
     */
    private $flatten;

    public function __construct(iterable $mapping = [], bool $flatten = true)
    {
        $this->mapping = MappingCollection::fromIterable($mapping);
        $this->flatten = $flatten;
    }

    public function in(Config $config, iterable $record): iterable
    {
        $this->loadConfig($config);

        $flat = ArrayNormalizer::flatten($record);

        $mapped = [];
        foreach ($flat as $key => $value) {
            $mapping = $this->mapping->get($key);
            if ($mapping === null) {
                continue;
            }

            $newKey = $mapping->getMappedKey();

            $mapped[$newKey] = $value;
        }

        foreach ($this->mapping as $m) {
            $sorted[$m->getMappedKey()] = $mapped[$m->getMappedKey()] ?? '';
        }

        if (empty($sorted)) {
            return;
        }

        if (!$this->flatten) {
            $sorted = ArrayNormalizer::expand($sorted);
        }

        yield from $sorted;
    }

    public function out(Config $config, iterable $record): iterable
    {
        $this->loadConfig($config);

        $flat = [];

        if (!$this->flatten) {
            $record = ArrayNormalizer::flatten($record);
        }

        foreach ($record as $key => $value) {
            $newKey = $this->mapKey($key);
            if ($newKey === null) {
                continue;
            }
            $flat[$newKey] = $value;
        }

        yield from ArrayNormalizer::expand($flat);
    }

    private function mapKey(string $key): ?string
    {
        $mapping = $this->mapping->getMapped($key);
        if ($mapping === null) {
            return null;
        }

        return $mapping->getKey();
    }

    private function loadConfig(Config $config): void
    {
        $this->mapping = $config->getMapping();
        $this->flatten = (bool) ($config->get('flatten') ?? true);
    }
}
