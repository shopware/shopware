<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\CallbackOperatorSecurityNodeVisitor;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('framework')]
class SecurityExtension extends AbstractExtension
{
    /**
     * @param array<string> $allowedPHPFunctions
     */
    public function __construct(private readonly array $allowedPHPFunctions)
    {
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('map', $this->map(...)),
            new TwigFilter('reduce', $this->reduce(...)),
            new TwigFilter('filter', $this->filter(...)),
            new TwigFilter('sort', $this->sort(...)),
            new TwigFilter('find', $this->find(...)),
        ];
    }

    /**
     * @return NodeVisitorInterface[]
     */
    public function getNodeVisitors(): array
    {
        return [
            new CallbackOperatorSecurityNodeVisitor(),
        ];
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable(mixed): mixed|\Closure $function
     *
     * @return array<mixed>
     */
    public function map(?iterable $array, string|callable|\Closure $function): ?array
    {
        if ($array === null) {
            return null;
        }

        if (\is_array($function)) {
            $function = implode('::', $function);
        }

        if (\is_string($function) && !\in_array($function, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(\sprintf('Function "%s" is not allowed', $function));
        }

        $result = [];
        foreach ($array as $key => $value) {
            // @phpstan-ignore-next-line
            $result[$key] = $function($value);
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable(mixed): mixed|\Closure $function
     */
    public function reduce(?iterable $array, string|callable|\Closure $function, mixed $initial = null): mixed
    {
        if ($array === null) {
            return null;
        }

        if (\is_array($function)) {
            $function = implode('::', $function);
        }

        if (\is_string($function) && !\in_array($function, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(\sprintf('Function "%s" is not allowed', $function));
        }

        if (!\is_array($array)) {
            $array = iterator_to_array($array);
        }

        // @phpstan-ignore-next-line
        return array_reduce($array, $function, $initial);
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable(mixed): mixed|\Closure $arrow
     *
     * @return iterable<mixed>
     */
    public function filter(?iterable $array, string|callable|\Closure $arrow): ?iterable
    {
        if ($array === null) {
            return null;
        }

        if (\is_array($arrow)) {
            $arrow = implode('::', $arrow);
        }

        if (\is_string($arrow) && !\in_array($arrow, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(\sprintf('Function "%s" is not allowed', $arrow));
        }

        if (\is_array($array)) {
            // @phpstan-ignore-next-line
            return array_filter($array, $arrow, \ARRAY_FILTER_USE_BOTH);
        }

        // @phpstan-ignore-next-line
        return new \CallbackFilterIterator(new \IteratorIterator($array), $arrow);
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable(mixed): mixed|\Closure $arrow
     *
     * @return array<mixed>
     */
    public function sort(?iterable $array, string|callable|\Closure|null $arrow = null): ?array
    {
        if ($array === null) {
            return null;
        }

        if (\is_array($arrow)) {
            $arrow = implode('::', $arrow);
        }

        if (\is_string($arrow) && !\in_array($arrow, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(\sprintf('Function "%s" is not allowed', $arrow));
        }

        if ($array instanceof \Traversable) {
            $array = iterator_to_array($array);
        }

        if ($arrow !== null) {
            // @phpstan-ignore-next-line
            uasort($array, $arrow);
        } else {
            asort($array);
        }

        return $array;
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable(mixed): bool|\Closure $arrow
     */
    public function find(?iterable $array, string|callable|\Closure $arrow): mixed
    {
        if ($array === null) {
            return null;
        }

        if (\is_array($arrow)) {
            $arrow = implode('::', \array_map('\strval', $arrow));
        }

        if (\is_string($arrow) && !\in_array($arrow, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(\sprintf('Function "%s" is not allowed', $arrow));
        }

        if (!\is_callable($arrow)) {
            return null;
        }

        if ($array instanceof \Traversable) {
            $array = iterator_to_array($array);
        }

        // mirror the map filter: custom string functions receive only the value
        $arrowCallback = \is_string($arrow)
            ? static fn (mixed $value): bool => (bool) \call_user_func($arrow, $value)
            : static fn (mixed $value, mixed $key): bool => (bool) \call_user_func($arrow, $value, $key);

        return \array_find($array, $arrowCallback);
    }

    /**
     * Guards callbacks passed to Twig core operators (e.g. "has some"/"has every") that Twig itself
     * only restricts in sandbox mode. Applies the same allowed-function policy as the filters above.
     *
     * @param string|callable|\Closure $callback
     */
    public function guardCallback(mixed $callback): \Closure
    {
        if ($callback instanceof \Closure) {
            return $callback;
        }

        if (\is_array($callback)) {
            $callback = implode('::', \array_map('\strval', $callback));
        }

        if (\is_string($callback) && !\in_array($callback, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(\sprintf('Function "%s" is not allowed', $callback));
        }

        if (!\is_callable($callback)) {
            // mirror the filters: a non-callable arrow simply never matches
            return static fn (): bool => false;
        }

        if (\is_string($callback)) {
            // mirror the map filter: custom string functions receive only the value, never the key
            return static fn (mixed $value): mixed => \call_user_func($callback, $value);
        }

        return \Closure::fromCallable($callback);
    }
}
