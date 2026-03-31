<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements \IteratorAggregate<string, TwigComponent>
 */
#[Package('framework')]
class TwigComponentCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var array<string, TwigComponent>
     */
    private array $elements = [];

    /**
     * @param iterable<TwigComponent> $elements
     */
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function add(TwigComponent $element): void
    {
        $this->elements[$element->getTag()] = $element;
    }

    public function get(string $key): ?TwigComponent
    {
        return $this->elements[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->elements[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->elements[$key]);
    }

    public function count(): int
    {
        return \count($this->elements);
    }

    /**
     * @return \ArrayIterator<string, TwigComponent>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->elements);
    }
}
