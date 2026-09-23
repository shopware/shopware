<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
interface Dependency
{
    public function value(): string;
}

class SystemUnderTest
{
    public function __construct(private readonly Dependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->value();
    }
}

class Fixture
{
    public function __construct(public readonly Dependency $dependency, public readonly SystemUnderTest $sut)
    {
    }
}

/**
 * @internal
 */
class Cases extends TestCase
{
    private Dependency $remembered;

    public function testStubInjectedIntoSut(): void
    {
        // FLAGGED: pure stub (method/willReturn), injected into the SUT, never ->expects().
        $dependency = $this->createMock(Dependency::class);
        $dependency->method('value')->willReturn('stub');

        $sut = new SystemUnderTest($dependency);

        static::assertSame('stub', $sut->run());
    }

    public function testRealMockWithExpectations(): void
    {
        // NOT flagged: ->expects() makes it a real mock.
        $dependency = $this->createMock(Dependency::class);
        $dependency->expects($this->once())->method('value')->willReturn('mock');

        $sut = new SystemUnderTest($dependency);

        static::assertSame('mock', $sut->run());
    }

    public function testMockPassedToOwnHelper(): void
    {
        // NOT flagged: the helper does configure an expectation on it.
        $dependency = $this->createMock(Dependency::class);
        $this->configure($dependency);

        $sut = new SystemUnderTest($dependency);

        static::assertSame('helper', $sut->run());
    }

    public function testInlineStubInjectedIntoSut(): void
    {
        // FLAGGED: inline createMock passed straight into the SUT — it can never be ->expects()-ed.
        $sut = new SystemUnderTest($this->createMock(Dependency::class));

        static::assertIsString($sut->run());
    }

    public function testInlineMockWithExpectation(): void
    {
        // NOT flagged: inline createMock immediately ->expects()-ed is a real mock.
        $dependency = $this->createMock(Dependency::class);
        $dependency->expects($this->once())->method('value')->willReturn('x');

        static::assertSame('x', (new SystemUnderTest($dependency))->run());
    }

    public function testStubForwardedIntoSutByHelper(): void
    {
        // FLAGGED: createSut() only forwards the double into the SUT constructor, so no expectation can be
        // hiding in there — the shape of every `createController(dep: $double)` fixture helper.
        $dependency = $this->createMock(Dependency::class);
        $dependency->method('value')->willReturn('stub');

        $sut = $this->createSut(dependency: $dependency);

        static::assertSame('stub', $sut->run());
    }

    public function testStubForwardedThroughTwoHelpers(): void
    {
        // FLAGGED: forwarding is followed transitively.
        $dependency = $this->createMock(Dependency::class);
        $dependency->method('value')->willReturn('stub');

        static::assertSame('stub', $this->buildSut($dependency)->run());
    }

    public function testMockRememberedByHelper(): void
    {
        // NOT flagged: the helper parks the double on a property, where any other method could ->expects() it.
        $dependency = $this->createMock(Dependency::class);
        $this->remember($dependency);

        static::assertIsString((new SystemUnderTest($dependency))->run());
    }

    public function testMockPassedToInheritedMethod(): void
    {
        // FLAGGED: assertNotNull() is an inherited PHPUnit assertion — it only reads the double
        // and cannot configure an expectation on it.
        $dependency = $this->createMock(Dependency::class);
        $dependency->method('value')->willReturn('stub');

        static::assertNotNull($dependency);
    }

    public function testMockExpectedThroughFixtureAlias(): void
    {
        // NOT flagged: the expectation is configured through a reference the rule cannot resolve back to a
        // double, so it must not assume the forwarding helper leaves this one bare.
        $dependency = $this->createMock(Dependency::class);
        $fixture = $this->createFixture($dependency);

        $fixture->dependency->expects($this->once())->method('value')->willReturn('a');

        static::assertSame('a', $fixture->sut->run());
    }

    public function testMockFromReturningHelper(): void
    {
        // FLAGGED: makeDependency() hands its double back, and this only call site forwards it
        // into the SUT constructor — provably never ->expects()-ed.
        $sut = new SystemUnderTest($this->makeDependency());

        static::assertSame('made', $sut->run());
    }

    private function makeDependency(): Dependency
    {
        $dependency = $this->createMock(Dependency::class);
        $dependency->method('value')->willReturn('made');

        return $dependency;
    }

    private function configure(Dependency $dependency): void
    {
        // a helper that does set an expectation on the passed double
        $dependency->expects($this->once())->method('value')->willReturn('helper');
    }

    private function createSut(?Dependency $dependency = null): SystemUnderTest
    {
        return new SystemUnderTest($dependency ?? $this->remembered);
    }

    private function buildSut(Dependency $dependency): SystemUnderTest
    {
        return $this->createSut(dependency: $dependency);
    }

    private function remember(Dependency $dependency): void
    {
        $this->remembered = $dependency;
    }

    private function createFixture(Dependency $dependency): Fixture
    {
        return new Fixture($dependency, new SystemUnderTest($dependency));
    }
}
