<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Storage;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
abstract class AbstractKeyValueStorage
{
    abstract public function has(string $key): bool;

    abstract public function get(string $key, mixed $default = null): mixed;

    abstract public function set(string $key, mixed $value): void;

    abstract public function remove(string $key): void;
}
