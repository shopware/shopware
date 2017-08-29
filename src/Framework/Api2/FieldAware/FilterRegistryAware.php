<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\ApiFilter\FilterRegistry;

interface FilterRegistryAware
{
    public function setFilterRegistry(FilterRegistry $filterRegistry): void;

}