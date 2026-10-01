<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelLifecycleManagerInUnitTestsRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Composes the fixture trait that hides the lifecycle call; loaded by the rule test so PHPStan can analyse
 * the trait body in the scope of this class.
 */
/**
 * @internal
 */
class ComposedTraitCases extends TestCase
{
    use LifecycleFixtureBehaviour;

    public function testOne(): void
    {
        static::assertNotNull($this->container());
    }
}
