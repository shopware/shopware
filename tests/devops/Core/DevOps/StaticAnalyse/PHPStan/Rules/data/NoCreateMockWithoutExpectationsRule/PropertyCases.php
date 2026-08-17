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
 * The property is ->expects()-ed by a helper the calling test reaches through the own call graph, so the
 * expectation is attributed to that test. NOT flagged.
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

/**
 * @internal
 *
 * One test reaches the ->expects()-ing helper through a two-hop call chain, the other never does — the
 * bare test notices at runtime. FLAGGED as mixed, naming testBare().
 */
class InitHelperExpectsPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testCovered(): void
    {
        $this->prepareScenario();
        static::assertSame('a', $this->dependency->value());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->dependency->value());
    }

    private function prepareScenario(): void
    {
        $this->initDependency();
    }

    private function initDependency(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
    }
}

/**
 * @internal
 *
 * setUp() reaches the ->expects()-ing helper, so every test is covered. NOT flagged.
 */
class SetUpHelperExpectsPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
        $this->initDependency();
    }

    public function testOne(): void
    {
        static::assertSame('a', $this->dependency->value());
    }

    private function initDependency(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
    }
}

/**
 * @internal
 *
 * The helper only forwards the property into the SUT constructor, so it cannot cover a test: the test
 * without a direct ->expects() notices at runtime. FLAGGED as mixed, naming testBare().
 */
class HelperForwardedMixedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testCovered(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->createSut()->run());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->run());
    }

    private function createSut(): PropertySut
    {
        return new PropertySut($this->dependency);
    }
}

/**
 * @internal
 *
 * The helpers only configure the property as a stub and forward it into the SUT constructor —
 * provably no expectation. FLAGGED as stub.
 */
class HelperForwardedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testOne(): void
    {
        static::assertSame('a', $this->createSut()->run());
    }

    private function createSut(): PropertySut
    {
        $this->dependency->method('value')->willReturn('a');

        return new PropertySut($this->dependency);
    }
}

/**
 * @internal
 *
 * A helper hands the property to a call the rule cannot resolve. NOT flagged (conservative bail).
 */
class HelperEscapedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testOne(): void
    {
        $this->configureElsewhere();
        static::assertSame('a', $this->dependency->value());
    }

    private function configureElsewhere(): void
    {
        PropertyConfigurator::configure($this->dependency);
    }
}

/**
 * @internal
 *
 * The property is created in setUp() and re-created in a test, orphaning the setUp instance. FLAGGED as
 * orphaned on the setUp assignment, naming testReCreates().
 */
class ReCreatedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testReCreates(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
        $this->dependency->expects($this->once())->method('value')->willReturn('a');

        static::assertSame('a', (new PropertySut($this->dependency))->run());
    }
}

/**
 * @internal
 *
 * Re-created in one test, configured on the setUp instance in another. The setUp instance is still orphaned
 * in the re-creating test. FLAGGED as orphaned, naming only testReCreates().
 */
class PartiallyReCreatedPropertyCases extends TestCase
{
    private PropertyDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
    }

    public function testReCreates(): void
    {
        $this->dependency = $this->createMock(PropertyDependency::class);
        $this->dependency->expects($this->once())->method('value')->willReturn('a');

        static::assertSame('a', (new PropertySut($this->dependency))->run());
    }

    public function testConfiguresSetUpInstance(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('b');

        static::assertSame('b', (new PropertySut($this->dependency))->run());
    }
}

/**
 * @internal
 */
class PropertySut
{
    public function __construct(private readonly PropertyDependency $dependency)
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
class PropertyConfigurator
{
    public static function configure(PropertyDependency $dependency): void
    {
    }
}
