<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

/**
 * @internal
 */
final class SkipSetUp
{
    protected function setUp()
    {
        return 100;
    }

    protected function setUpMyObject()
    {
        return 100;
    }
}
