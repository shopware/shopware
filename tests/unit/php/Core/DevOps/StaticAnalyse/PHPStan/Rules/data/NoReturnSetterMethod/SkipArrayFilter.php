<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

/**
 * @internal
 */
final class SkipArrayFilter
{
    public function setItems(array $items): void
    {
        array_map(function ($item) {
            return $item;
        }, array_filter($items));
    }
}
