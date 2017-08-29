<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\UuidGenerator\GeneratorRegistry;

interface UuidGeneratorRegistryAware
{
    public function setUuidGeneratorRegistry(GeneratorRegistry $generatorRegistry): void;
}