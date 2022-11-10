<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

/**
 * @package core
 */
interface StorageAware
{
    public function getStorageName(): string;
}
