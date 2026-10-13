<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReflectionInUnitTestsRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CasesOutOfScope extends TestCase
{
    public function testReflection(object $subject): void
    {
        new \ReflectionClass($subject);
        \Closure::bind(static fn () => null, null, $subject::class);
    }
}
