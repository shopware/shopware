<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
class SecurityExtension extends AbstractExtension
{
    /**
     * @var array<string>
     */
    private array $allowedPHPFunctions;

    /**
     * @param array<string> $allowedPHPFunctions
     */
    public function __construct(array $allowedPHPFunctions)
    {
        $this->allowedPHPFunctions = $allowedPHPFunctions;
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('map', [$this, 'map']),
            new TwigFilter('reduce', [$this, 'reduce']),
            new TwigFilter('filter', [$this, 'filter']),
            new TwigFilter('sort', [$this, 'sort']),
        ];
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable|\Closure $function
     *
     * @return array<mixed>
     */
    public function map(iterable $array, $function): array
    {
        if (\is_string($function) && !\in_array($function, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(sprintf('Function "%s" is not allowed', $function));
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
     * @param string|callable|\Closure $function
     * @param mixed $initial
     *
     * @return mixed
     */
    public function reduce(iterable $array, $function, $initial = null)
    {
        if (\is_string($function) && !\in_array($function, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(sprintf('Function "%s" is not allowed', $function));
        }

        if (!\is_array($array)) {
            $array = iterator_to_array($array);
        }

        // @phpstan-ignore-next-line
        return array_reduce($array, $function, $initial);
    }

    /**
     * @param iterable<mixed> $array
     * @param string|callable|\Closure $arrow
     *
     * @return iterable<mixed>
     */
    public function filter(iterable $array, $arrow): iterable
    {
        if (\is_string($arrow) && !\in_array($arrow, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(sprintf('Function "%s" is not allowed', $arrow));
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
     * @param string|callable|\Closure|null $arrow
     *
     * @return array<mixed>
     */
    public function sort(iterable $array, $arrow = null): array
    {
        if (\is_string($arrow) && !\in_array($arrow, $this->allowedPHPFunctions, true)) {
            throw new \RuntimeException(sprintf('Function "%s" is not allowed', $arrow));
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
}
