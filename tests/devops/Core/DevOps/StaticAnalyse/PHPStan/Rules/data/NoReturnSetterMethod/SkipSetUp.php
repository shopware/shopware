<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

/**
 * @internal
 */
final class SkipSetUp
{
    private function setUp()
    {
        return 100;
    }

    private function setUpMyObject()
    {
        return 100;
    }
}
