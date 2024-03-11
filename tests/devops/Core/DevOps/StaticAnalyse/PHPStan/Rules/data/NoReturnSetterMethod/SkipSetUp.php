<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

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
