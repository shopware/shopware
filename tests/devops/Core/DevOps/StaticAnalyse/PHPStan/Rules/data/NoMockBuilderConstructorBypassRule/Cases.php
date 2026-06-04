<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoMockBuilderConstructorBypassRule;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
interface SomeService
{
    public function doIt(): string;

    public function other(): int;
}

/**
 * @internal
 */
class Cases extends TestCase
{
    public function testRedundant(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->disableOriginalConstructor()->getMock();
        static::assertNotNull($mock);
    }

    public function testPartial(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->disableOriginalConstructor()->onlyMethods(['doIt'])->getMock();
        static::assertNotNull($mock);
    }

    public function testConstructorArgsIsNotFlagged(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->setConstructorArgs([])->getMock();
        static::assertNotNull($mock);
    }

    public function testPartialWithoutDisableIsNotFlagged(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->onlyMethods(['doIt'])->getMock();
        static::assertNotNull($mock);
    }

    public function testCreateMockIsNotFlagged(): void
    {
        $mock = $this->createMock(SomeService::class);
        static::assertNotNull($mock);
    }
}
