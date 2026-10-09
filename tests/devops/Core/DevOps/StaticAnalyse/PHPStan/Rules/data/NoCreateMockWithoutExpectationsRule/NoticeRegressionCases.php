<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SwagFixtureProduction\NoCreateMockWithoutExpectationsRule\ProductionDependency;
use SwagFixtureProduction\NoCreateMockWithoutExpectationsRule\ProductionSut;

interface FluentDependency
{
    public function with(string $key): self;

    public function value(): string;
}

interface GuardDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * The double captures ITSELF in its willReturnCallback() closure to emulate a fluent API. The closure's
 * `return $builder` ends the closure, not the test method, so nothing escapes to a caller. FLAGGED.
 */
class ClosureCapturedFluentCases extends TestCase
{
    public function testOne(): void
    {
        $builder = $this->createMock(FluentDependency::class);
        $builder->method('with')->willReturnCallback(static function () use ($builder): FluentDependency {
            return $builder;
        });
        $builder->method('value')->willReturn('a');

        static::assertSame('a', (new FluentSut($builder))->run());
    }
}

/**
 * @internal
 *
 * The fixture helper defaults its nullable parameter inside an `if ($dependency === null)` guard before
 * forwarding it into the SUT. When the test's double is bound, the guard branch never runs, so the double
 * only flows into production code. FLAGGED.
 */
class NullGuardedDefaultParamCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->createMock(GuardDependency::class);
        $dependency->method('value')->willReturn('a');

        [$sut] = $this->createSut(dependency: $dependency);

        static::assertSame('a', $sut->run());
    }

    /**
     * @return array{0: GuardSut}
     */
    private function createSut(?GuardDependency $dependency = null): array
    {
        if ($dependency === null) {
            $dependency = static::createStub(GuardDependency::class);
            $dependency->method('value')->willReturn('');
        }

        return [new GuardSut($dependency)];
    }
}

/**
 * @internal
 *
 * The helper wraps its double into the returned SUT: production code cannot configure expectations and
 * does not re-expose the double, so nothing reaches the callers. FLAGGED.
 */
class SutReturningHelperCases extends TestCase
{
    public function testOne(): void
    {
        static::assertSame('a', $this->buildSut()->run());
    }

    private function buildSut(): ProductionSut
    {
        $dependency = $this->createMock(ProductionDependency::class);
        $dependency->method('value')->willReturn('a');

        return new ProductionSut($dependency);
    }
}

/**
 * @internal
 *
 * The helper wraps its double into a returned test-namespace fixture struct, whose public property hands
 * the double back to the caller for a potential ->expects(). NOT flagged (conservative skip).
 */
class FixtureReturningHelperCases extends TestCase
{
    public function testOne(): void
    {
        $fixture = $this->buildFixture();
        $fixture->dependency->expects($this->once())->method('value')->willReturn('a');

        static::assertSame('a', $fixture->sut->run());
    }

    public function testTwo(): void
    {
        static::assertSame('', $this->buildFixture()->sut->run());
    }

    private function buildFixture(): GuardFixture
    {
        $dependency = $this->createMock(GuardDependency::class);

        return new GuardFixture($dependency, new GuardSut($dependency));
    }
}

/**
 * @internal
 *
 * A closure that leaves the method through a `return` carries its captured double with it. NOT flagged
 * (the caller can invoke the closure and ->expects() the result).
 */
class ReturnedClosureCases extends TestCase
{
    public function testOne(): void
    {
        $factory = $this->makeFactory();
        $dependency = $factory();
        $dependency->expects($this->once())->method('value')->willReturn('a');

        static::assertSame('a', (new GuardSut($dependency))->run());
    }

    /**
     * @return callable(): (GuardDependency&MockObject)
     */
    private function makeFactory(): callable
    {
        $dependency = $this->createMock(GuardDependency::class);

        return static function () use ($dependency) {
            return $dependency;
        };
    }
}

/**
 * @internal
 */
class FluentSut
{
    public function __construct(private readonly FluentDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->with('key')->value();
    }
}

/**
 * @internal
 */
class GuardSut
{
    public function __construct(private readonly GuardDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->value();
    }
}

/**
 * @internal
 */
class GuardFixture
{
    public function __construct(
        public readonly GuardDependency $dependency,
        public readonly GuardSut $sut,
    ) {
    }
}
