<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\FieldAware\ResourceAware;
use Shopware\Framework\Api2\FieldAware\UuidGeneratorRegistryAware;
use Shopware\Framework\Api2\FieldAware\WriteContextAware;
use Shopware\Framework\Api2\Resource\ApiResource;
use Shopware\Framework\Api2\UuidGenerator\GeneratorRegistry;
use Shopware\Framework\Api2\UuidGenerator\RamseyGenerator;
use Shopware\Framework\Api2\WriteContext;

class UuidField extends Field implements WriteContextAware, ResourceAware, UuidGeneratorRegistryAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var ApiResource
     */
    private $resource;

    /**
     * @var GeneratorRegistry
     */
    private $generatorRegistry;
    /**
     * @var string
     */
    private $generatorClass;

    /**
     * @param string $storageName
     */
    public function __construct(string $storageName, string $generatorClass = RamseyGenerator::class)
    {
        $this->storageName = $storageName;
        $this->generatorClass = $generatorClass;
    }

    /**
     * @param WriteContext $writeContext
     */
    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    /**
     * @param ApiResource $resource
     */
    public function setResource(ApiResource $resource): void
    {
        $this->resource = $resource;
    }


    public function setUuidGeneratorRegistry(GeneratorRegistry $generatorRegistry): void
    {
        $this->generatorRegistry = $generatorRegistry;
    }


    /**
     * @param string $key
     * @param null $value
     * @return \Generator
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!$value) {
            $value = $this->generatorRegistry
                ->get($this->generatorClass)
                ->create();
        }

        $this->writeContext->set(get_class($this->resource), $key, $value);

        yield $this->storageName => $value;
    }

}