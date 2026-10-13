<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

interface ChainDependency
{
    public function get(string $key): ?string;

    public function save(string $key, string $value): void;
}

interface ChainBuilder
{
    public function with(string $key): self;

    public function value(): string;
}

/**
 * @internal
 *
 * PHPUnit 13 deprecates `->with()` on a chain that carries no `->expects()`, per chain. The double's origin
 * and its other chains do not matter.
 */
class WithWithoutExpectsCases extends TestCase
{
    private MockObject&ChainDependency $shared;

    protected function setUp(): void
    {
        $this->shared = $this->getMockBuilder(ChainDependency::class)->getMock();
    }

    public function testExpectsOnTheSameChain(): void
    {
        $dependency = $this->createMock(ChainDependency::class);
        $dependency->expects($this->once())->method('get')->with('key')->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }

    public function testExpectsOnAnotherChainOnly(): void
    {
        $dependency = $this->createMock(ChainDependency::class);
        $dependency->expects($this->once())->method('save');
        $dependency->method('get')->with('key')->willReturn('value');

        $dependency->save('key', 'value');
        static::assertSame('value', $dependency->get('key'));
    }

    public function testBuilderCreatedDouble(): void
    {
        $dependency = $this->getMockBuilder(ChainDependency::class)->getMock();
        $dependency->method('get')->with('key')->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }

    public function testMultiLineChainOnSharedDouble(): void
    {
        $this->shared
            ->method('get')
            ->with('key')
            ->willReturn('value');

        static::assertSame('value', $this->shared->get('key'));
    }

    public function testWithAnyParameters(): void
    {
        $dependency = $this->getMockBuilder(ChainDependency::class)->getMock();
        $dependency->method('get')->withAnyParameters()->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }

    public function testForeignFluentWithIsNotAMatcher(): void
    {
        $builder = $this->createMock(ChainBuilder::class);
        $builder->expects($this->once())->method('with')->willReturnSelf();
        $builder->method('value')->willReturn('value');

        static::assertSame('value', $builder->with('key')->value());
    }
}

/**
 * @internal
 *
 * PHPUnit 13 deprecates `->atLeast()` with a lower bound that cannot fail.
 */
class AtLeastCases extends TestCase
{
    public function testPositiveBound(): void
    {
        $dependency = $this->createMock(ChainDependency::class);
        $dependency->expects($this->atLeast(1))->method('get')->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }

    public function testZeroBound(): void
    {
        $dependency = $this->createMock(ChainDependency::class);
        $dependency->expects($this->atLeast(0))->method('get')->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }

    public function testNegativeBound(): void
    {
        $dependency = $this->createMock(ChainDependency::class);
        $dependency->expects($this->atLeast(-1))->method('get')->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }

    public function testDynamicBoundIsNotResolved(): void
    {
        $bound = random_int(1, 3);
        $dependency = $this->createMock(ChainDependency::class);
        $dependency->expects($this->atLeast($bound))->method('get')->willReturn('value');

        static::assertSame('value', $dependency->get('key'));
    }
}
