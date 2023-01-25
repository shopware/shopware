<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractIncrementStorage
{
    /**
     * Reserves and fetches the next increment atomically
     *
     * @param array{id: string, pattern: string, start: ?int} $config
     */
    abstract public function reserve(array $config): int;

    /**
     * Fetches the next increment value without reserving it
     *
     * @param array{id: string, pattern: string, start: ?int} $config
     */
    abstract public function preview(array $config): int;

    /**
     * Lists the current increment states, indexed by the number range configuration id
     *
     * @return array<string, int>
     */
    abstract public function list(): array;

    /**
     * Sets the current increment state to the given value for the given number range configuration.
     * Mainly used for migrating between different increment storages.
     * Note: Calling this method and overwriting the current increment state may lead to duplicated increments!
     */
    abstract public function set(string $configurationId, int $value): void;

    abstract public function getDecorated(): self;
}
