<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

class ProtectionStruct implements \IteratorAggregate
{
    /**
     * @var bool[]
     */
    private $keys;

    public function __construct(string ...$keys)
    {
        $this->keys = array_flip($keys);
    }

    public function allow(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->keys[$key] = true;
        }
    }

    public function disallow(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->keys[$key]);
        }
    }

    public function isAllowed(string $key): bool
    {
        return isset($this->keys[$key]);
    }

    public function all(): array
    {
        return array_flip($this->keys);
    }

    public function getIterator(): iterable
    {
        return new \ArrayIterator(array_flip($this->keys));
    }
}
