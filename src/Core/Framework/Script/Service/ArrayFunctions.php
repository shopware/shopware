<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Service;

class ArrayFunctions
{
    private array $items;

    public function __construct(array &$items)
    {
        $this->items = &$items;
    }

    /**
     * @param string|bool|float|int|array|null $value
     */
    public function add($value): void
    {
        $this->items[] = $value;
    }

    /**
     * @param string|bool|float|int|array|null $value
     */
    public function remove($value): void
    {
        $index = array_search($value, $this->items, true);

        if (\is_int($index)) {
            unset($this->items[$index]);
        }
    }

    /**
     * @param string|bool|float|int|array|null $value
     */
    public function has($value): bool
    {
        return \in_array($value, $this->items, true);
    }
}
