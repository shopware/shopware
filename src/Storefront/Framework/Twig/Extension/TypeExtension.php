<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

class TypeExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return ['of_type' => new TwigTest('of_type', $this->ofType(...))];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('intval', fn ($value) => intval($value)),
        ];
    }

    public function ofType(mixed $var, string $test, ?string $class = null): bool
    {
        return match ($test) {
            'array' => \is_array($var),
            'bool' => \is_bool($var),
            'object' => \is_object($var),
            'class' => \is_object($var) && $class === \get_class($var),
            'float' => \is_float($var),
            'int' => \is_int($var),
            'numeric' => is_numeric($var),
            'scalar' => \is_scalar($var),
            'string' => \is_string($var),
            default => throw new \InvalidArgumentException(\sprintf('Invalid "%s" type test.', $test)),
        };
    }
}