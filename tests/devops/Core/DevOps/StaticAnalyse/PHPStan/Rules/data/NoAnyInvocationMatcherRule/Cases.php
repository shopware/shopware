<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAnyInvocationMatcherRule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
interface SomeService
{
    public function doIt(): string;
}

/**
 * A mock-building helper that is NOT a TestCase subtype — mirrors the support traits/classes under
 * src/**\/Test. The rule keys off the structural expects(any()) shape rather than the receiver type,
 * so it fires here too. Because it is not a TestCase, php-cs-fixer leaves the static::/self:: matcher
 * calls untouched, which lets us exercise the StaticCall branch of the rule.
 *
 * @internal
 */
class NotATestCase
{
    public static function any(): object
    {
        return new \stdClass();
    }

    public static function never(): object
    {
        return new \stdClass();
    }

    public function configureStatic(MockObject&SomeService $mock): void
    {
        $mock->expects(static::any())->method('doIt')->willReturn('x');
    }

    public function configureSelf(MockObject&SomeService $mock): void
    {
        $mock->expects(self::any())->method('doIt')->willReturn('x');
    }

    public function configureNever(MockObject&SomeService $mock): void
    {
        $mock->expects(static::never())->method('doIt');
    }
}

/**
 * @internal
 */
class Cases extends TestCase
{
    public function testThisAnyIsFlagged(): void
    {
        $mock = $this->createMock(SomeService::class);
        $mock->expects($this->any())->method('doIt')->willReturn('x');
        static::assertSame('x', $mock->doIt());
    }

    public function testOnceIsNotFlagged(): void
    {
        $mock = $this->createMock(SomeService::class);
        $mock->expects($this->once())->method('doIt')->willReturn('x');
        static::assertSame('x', $mock->doIt());
    }

    public function testBareMethodIsNotFlagged(): void
    {
        $mock = $this->createMock(SomeService::class);
        $mock->method('doIt')->willReturn('x');
        static::assertSame('x', $mock->doIt());
    }
}

/**
 * A non-PHPUnit fluent API that coincidentally exposes expects()/any(). After narrowing the rule to
 * PHPUnit MockObject receivers, this must NOT be flagged (guards the false-positive concern from review).
 *
 * @internal
 */
class NotAMock
{
    public function expects(object $matcher): self
    {
        return $this;
    }

    public function any(): object
    {
        return new \stdClass();
    }

    public function method(string $name): self
    {
        return $this;
    }

    public function notFlagged(): void
    {
        $this->expects($this->any())->method('doIt');
    }
}
