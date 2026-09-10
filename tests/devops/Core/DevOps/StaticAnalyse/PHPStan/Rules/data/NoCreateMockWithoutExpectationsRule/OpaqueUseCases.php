<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface OpaqueDependency
{
    public function value(): string;
}

interface OpaqueLocator
{
    public function get(string $key): OpaqueDependency;
}

/**
 * @internal
 *
 * The helper embeds the property in another double's willReturnMap() — data consumption, not a hidden
 * expectation. FLAGGED as mixed, naming testBare().
 */
class WillReturnMapEmbeddedPropertyCases extends TestCase
{
    private OpaqueDependency $dependency;

    private OpaqueLocator $locator;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
        $this->locator = $this->createMock(OpaqueLocator::class);
    }

    public function testCovered(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->createSut()->lookup());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->lookup());
    }

    private function createSut(): OpaqueLocatorSut
    {
        $this->locator->method('get')->willReturnMap([['key', $this->dependency]]);

        return new OpaqueLocatorSut($this->locator);
    }
}

/**
 * @internal
 *
 * The property is created inside a fixture helper instead of setUp(): the tests reaching that helper own
 * an instance. FLAGGED as mixed, naming testBare() — testNotOwning never creates the double and stays out.
 */
class HelperCreatedPropertyCases extends TestCase
{
    private OpaqueDependency $dependency;

    public function testCovered(): void
    {
        $sut = $this->createSut();
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $sut->run());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->run());
    }

    public function testNotOwning(): void
    {
        static::assertTrue(true);
    }

    private function createSut(): OpaqueSut
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);

        return new OpaqueSut($this->dependency);
    }
}

/**
 * @internal
 *
 * The property reaches the SUT constructor wrapped in an array literal. FLAGGED as mixed, naming testBare().
 */
class ArrayWrappedPropertyCases extends TestCase
{
    private OpaqueDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
    }

    public function testCovered(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->createSut()->first());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->first());
    }

    private function createSut(): OpaqueCollectionSut
    {
        return new OpaqueCollectionSut(['dep' => $this->dependency]);
    }
}

/**
 * @internal
 *
 * The fixture helper re-binds its parameter with `$dep = $dep ?? <fresh stub>` before forwarding it into
 * the SUT — the double still only flows into production code. FLAGGED as stub.
 */
class SelfDefaultingParamCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->createMock(OpaqueDependency::class);
        $dependency->method('value')->willReturn('a');

        static::assertSame('a', $this->createSut($dependency)->run());
    }

    private function createSut(?OpaqueDependency $dependency = null): OpaqueSut
    {
        $dependency = $dependency ?? static::createStub(OpaqueDependency::class);

        return new OpaqueSut($dependency);
    }
}

/**
 * @internal
 *
 * A helper aliases the property into a local that stays clean (constructor forwarding only). FLAGGED as stub.
 */
class AliasFollowedPropertyCases extends TestCase
{
    private OpaqueDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
    }

    public function testOne(): void
    {
        static::assertSame('', $this->createSut()->run());
    }

    private function createSut(): OpaqueSut
    {
        $dependency = $this->dependency;

        return new OpaqueSut($dependency);
    }
}

/**
 * @internal
 *
 * The alias itself gets ->expects()-ed — the property must keep its conservative skip. NOT flagged.
 */
class AliasExpectedPropertyCases extends TestCase
{
    private OpaqueDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
    }

    public function testOne(): void
    {
        $this->configureAlias();
        static::assertSame('a', $this->dependency->value());
    }

    public function testTwo(): void
    {
        static::assertSame('', $this->dependency->value());
    }

    private function configureAlias(): void
    {
        $dependency = $this->dependency;
        $dependency->expects($this->once())->method('value')->willReturn('a');
    }
}

/**
 * @internal
 */
class OpaqueSut
{
    public function __construct(private readonly OpaqueDependency $dependency)
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
class OpaqueLocatorSut
{
    public function __construct(private readonly OpaqueLocator $locator)
    {
    }

    public function lookup(): string
    {
        return $this->locator->get('key')->value();
    }
}

/**
 * @internal
 */
class OpaqueCollectionSut
{
    /**
     * @param array<string, OpaqueDependency> $dependencies
     */
    public function __construct(private readonly array $dependencies)
    {
    }

    public function first(): string
    {
        foreach ($this->dependencies as $dependency) {
            return $dependency->value();
        }

        return '';
    }
}
