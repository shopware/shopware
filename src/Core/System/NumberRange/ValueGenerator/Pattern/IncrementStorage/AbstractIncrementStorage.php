<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

abstract class AbstractIncrementStorage
{
    /**
     * @param array{id: string, pattern: string, start: ?int} $config
     */
    abstract public function reserve(array $config): string;

    /**
     * @param array{id: string, pattern: string, start: ?int} $config
     */
    abstract public function preview(array $config): string;

    abstract public function getDecorated(): self;
}
