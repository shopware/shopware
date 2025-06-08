<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface StorageAware
{
    public function getStorageName(): string;
}
