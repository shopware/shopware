<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Filter;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ReplaceRecursiveFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('replace_recursive', [$this, 'replaceRecursive']),
        ];
    }

    public function replaceRecursive(array ...$params): array
    {
        return array_replace_recursive(...$params);
    }
}
