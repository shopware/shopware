<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReflectionInUnitTestsRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class Cases extends TestCase
{
    public function testReflectionClasses(object $subject): void
    {
        new \ReflectionClass($subject);
        new \ReflectionProperty($subject, 'secret');
        new \ReflectionMethod($subject, 'compute');
        new \ReflectionObject($subject);
        new \ReflectionFunction('strlen');
        new \ReflectionClassConstant($subject, 'LIMIT');
    }

    public function testClosureBinding(object $subject): void
    {
        $read = fn () => $this->secret ?? null;

        \Closure::bind($read, $subject, $subject::class);
        $read->bindTo($subject, $subject::class);
        $read->call($subject);
    }

    public function testAllowed(object $subject): void
    {
        $read = static fn (): string => 'value';

        $read(); // invoking a closure is fine
        \call_user_func($read); // so is calling it indirectly
        static::assertSame(\ArrayObject::class, $subject::class); // class names are no reflection
    }
}

/**
 * @internal
 */
class Helper
{
    public function inspect(object $subject): void
    {
        new \ReflectionClass($subject); // helpers inside the unit namespace are covered too
    }
}
