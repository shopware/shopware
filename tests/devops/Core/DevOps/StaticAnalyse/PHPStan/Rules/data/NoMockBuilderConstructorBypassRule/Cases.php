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
    public function testPlainGetMockIsRedundant(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->getMock();
        static::assertNotNull($mock);
    }

    public function testStaticGetMockIsRedundant(): void
    {
        $mock = static::getMockBuilder(SomeService::class)->disableOriginalConstructor()->getMock();
        static::assertNotNull($mock);
    }

    public function testDisableConstructorIsRedundant(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->disableOriginalConstructor()->getMock();
        static::assertNotNull($mock);
    }

    public function testPartialIsNotFlagged(): void
    {
        $mock = $this->getMockBuilder(SomeService::class)->disableOriginalConstructor()->onlyMethods(['doIt'])->getMock();
        static::assertNotNull($mock);
    }

    public function testStaticPartialIsNotFlagged(): void
    {
        $mock = static::getMockBuilder(SomeService::class)->onlyMethods(['doIt'])->getMock();
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
