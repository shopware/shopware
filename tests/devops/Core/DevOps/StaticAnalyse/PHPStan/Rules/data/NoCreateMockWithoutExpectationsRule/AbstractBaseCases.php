<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface BaseDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * Abstract bases are never reported on their own — the notice fires per concrete class run, so the
 * subclasses below analyse these fixtures with all call sites in view.
 */
abstract class AbstractDependencyCases extends TestCase
{
    protected BaseDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(BaseDependency::class);
    }

    protected function fetchValue(): string
    {
        return (new BaseSut($this->dependency))->run();
    }

    protected function initDependency(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
    }

    protected function buildLocalDouble(): BaseSut
    {
        $local = $this->createMock(BaseDependency::class); // FLAGGED as stub once each subclass is analysed
        $local->method('value')->willReturn('b');
        $sut = new BaseSut($local);

        return $sut;
    }
}

/**
 * @internal
 *
 * The inherited property is ->expects()-ed in one test (via the inherited helper) but bare in the other.
 * FLAGGED as mixed at the base's createMock line, naming testBare().
 */
class MixedChildCases extends AbstractDependencyCases
{
    public function testCovered(): void
    {
        $this->initDependency();
        static::assertSame('a', $this->fetchValue());
    }

    public function testBare(): void
    {
        static::assertSame('b', $this->buildLocalDouble()->run());
    }
}

/**
 * @internal
 *
 * Every test reaches the inherited ->expects()-ing helper. NOT flagged for the property — but analysing
 * this subclass still reports the base helper's local double as a pure stub.
 */
class CoveredChildCases extends AbstractDependencyCases
{
    public function testOne(): void
    {
        $this->initDependency();
        static::assertSame('a', $this->fetchValue());
    }
}

/**
 * @internal
 */
class BaseSut
{
    public function __construct(private readonly BaseDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->value();
    }
}
