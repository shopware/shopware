<?php declare(strict_types=1);

namespace Shopware\Api\Write\FieldAware;

interface StorageAware
{
    public function getStorageName(): string;
}
