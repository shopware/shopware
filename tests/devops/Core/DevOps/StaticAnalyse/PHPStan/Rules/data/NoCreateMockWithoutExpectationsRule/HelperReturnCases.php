<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface ReturnDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * The helper returns the double directly and no call site ever ->expects()-es it. FLAGGED.
 */
class DirectReturnStubCases extends TestCase
{
    public function testOne(): void
    {
        $sut = new ReturnSut($this->getDependency());
        static::assertSame('', $sut->run());
    }

    private function getDependency(): ReturnDependency
    {
        return $this->createMock(ReturnDependency::class);
    }
}

/**
 * @internal
 *
 * The helper stub-configures a local and bare-returns it; the callers bind it to clean locals. FLAGGED.
 */
class ConfiguredReturnStubCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->getDependency();
        $sut = new ReturnSut($dependency);
        static::assertSame('a', $sut->run());
    }

    private function getDependency(): ReturnDependency
    {
        $dependency = $this->createMock(ReturnDependency::class);
        $dependency->method('value')->willReturn('a');

        return $dependency;
    }
}

/**
 * @internal
 *
 * A call site chains an ->expects() onto the helper result. NOT flagged (real mock).
 */
class ChainedExpectsReturnCases extends TestCase
{
    public function testOne(): void
    {
        $this->getDependency()->expects($this->once())->method('value');
    }

    public function testTwo(): void
    {
        $sut = new ReturnSut($this->getDependency());
        static::assertSame('', $sut->run());
    }

    private function getDependency(): ReturnDependency
    {
        return $this->createMock(ReturnDependency::class);
    }
}

/**
 * @internal
 *
 * A call site binds the result and ->expects()-es it later. NOT flagged (real mock).
 */
class BoundExpectsReturnCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->getDependency();
        $dependency->expects($this->once())->method('value');

        (new ReturnSut($dependency))->run();
    }

    private function getDependency(): ReturnDependency
    {
        return $this->createMock(ReturnDependency::class);
    }
}

/**
 * @internal
 *
 * A call site hands the result to a call the rule cannot resolve. NOT flagged (conservative bail).
 */
class EscapedReturnCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->getDependency();
        ReturnConfigurator::configure($dependency);
        static::assertSame('a', $dependency->value());
    }

    private function getDependency(): ReturnDependency
    {
        return $this->createMock(ReturnDependency::class);
    }
}

/**
 * @internal
 *
 * The double only feeds an inherited PHPUnit assertion, which reads its arguments and cannot
 * configure expectations. FLAGGED.
 */
class AssertionArgumentCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->createMock(ReturnDependency::class);
        $sut = new ReturnSut($dependency);

        static::assertSame($dependency, $sut->getDependency());
    }
}

/**
 * @internal
 *
 * An assert-named method declared in THIS class configures an expectation; the whitelist only
 * covers inherited assertions. NOT flagged.
 */
class OwnAssertHelperCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->createMock(ReturnDependency::class);
        (new ReturnSut($dependency))->run();

        $this->assertDependencyWasRead($dependency);
    }

    private function assertDependencyWasRead(ReturnDependency $dependency): void
    {
        $dependency->expects($this->once())->method('value');
    }
}

/**
 * @internal
 */
class ReturnSut
{
    public function __construct(private readonly ReturnDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->value();
    }

    public function getDependency(): ReturnDependency
    {
        return $this->dependency;
    }
}

/**
 * @internal
 */
class ReturnConfigurator
{
    public static function configure(ReturnDependency $dependency): void
    {
    }
}
