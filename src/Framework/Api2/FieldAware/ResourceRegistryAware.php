<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\Resource\ResourceRegistry;

interface ResourceRegistryAware
{
    public function setResourceRegistry(ResourceRegistry $resourceRegistry): void;
}