<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\FieldAware;

interface StorageAware
{
    public function getStorageName(): string;
}
