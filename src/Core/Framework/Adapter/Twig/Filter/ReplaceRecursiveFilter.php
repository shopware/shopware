<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Filter;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('core')]
class ReplaceRecursiveFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('replace_recursive', $this->replaceRecursive(...)),
        ];
    }

    /**
     * @param array<mixed> ...$params
     *
     * @return array<mixed>
     */
    public function replaceRecursive(array ...$params): array
    {
        return array_replace_recursive(...$params);
    }
}
