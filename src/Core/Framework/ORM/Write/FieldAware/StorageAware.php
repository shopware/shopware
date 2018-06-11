<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldAware;

interface StorageAware
{
    public function getStorageName(): string;
}
