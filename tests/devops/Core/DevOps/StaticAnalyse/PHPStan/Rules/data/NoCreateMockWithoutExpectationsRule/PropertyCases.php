<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface PropertyDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * Pure-stub property: never ->expects()-ed, so it notices in every test. FLAGGED (createStub).
 */
class PureStubPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testOne(): void
    {
        $this->dependency->method('value')->willReturn('a');
        static::assertSame('a', $this->dependency->value());
    }

    public function testTwo(): void
    {
        $this->dependency->method('value')->willReturn('b');
        static::assertSame('b', $this->dependency->value());
    }
}

/**
 * @internal
 *
 * Mixed property: ->expects()-ed in one test, left bare in another, so it notices in the latter. FLAGGED
 * (mixed-usage message — createStub would break the expects() test).
 */
class MixedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testWithExpectation(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->dependency->value());
    }

    public function testBare(): void
    {
        $this->dependency->method('value')->willReturn('b');
        static::assertSame('b', $this->dependency->value());
    }
}

/**
 * @internal
 *
 * Every test configures an expectation on the property, so it never notices. NOT flagged (FP-safety).
 */
class AllExpectedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testOne(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->dependency->value());
    }

    public function testTwo(): void
    {
        $this->dependency->expects($this->never())->method('value');
        static::assertTrue(true);
    }
}

/**
 * @internal
 *
 * The property is configured by a helper the rule cannot see into. NOT flagged (conservative bail).
 */
class HelperConfiguredPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testOne(): void
    {
        $this->configureExpectations();
        static::assertSame('a', $this->dependency->value());
    }

    private function configureExpectations(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
    }
}
