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

/**
 * @internal
 */
class Cases extends TestCase
{
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
        // NOT flagged (conservative): the helper might configure expectations on it.
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

    private function configure(Dependency $dependency): void
    {
        // a helper that does set an expectation on the passed double
        $dependency->method('value')->willReturn('helper');
    }
}
