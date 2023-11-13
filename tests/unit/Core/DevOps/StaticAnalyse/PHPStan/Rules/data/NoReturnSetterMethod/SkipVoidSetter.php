<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

/**
 * @internal
 */
final class SkipVoidSetter
{
    private $name;

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
