<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\Resource\ApiResource;

interface ResourceAware
{
    public function setResource(ApiResource $resource): void;
}