<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SystemConfigServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepositoryInterface
     */
    private $systemConfigRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->systemConfigRepository = $this->getContainer()->get('system_config.repository');
    }

    public function setGetDifferentTypesProvider(): array
    {
        return [
            [true],
            [false],
            [null],
            [0],
            [1234],
            [1243.42314],
            [''],
            ['test'],
            [['foo' => 'bar']],
        ];
    }

    /**
     * @param $expected mixed
     * @dataProvider setGetDifferentTypesProvider
     */
    public function testSetGetDifferentTypes($expected): void
    {
        $this->systemConfigService->set('foo.bar', $expected);
        $actual = $this->systemConfigService->get('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * mysql 5.7.30 casts 0.0 to 0
     */
    public function testFloatZero(): void
    {
        $this->systemConfigService->set('foo.bar', 0.0);
        $actual = $this->systemConfigService->get('foo.bar');
        static::assertEquals(0.0, $actual);
    }

    public function testSetGetSalesChannel(): void
    {
        $this->systemConfigService->set('foo.bar', 'test');
        $actual = $this->systemConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertEquals('test', $actual);

        $this->systemConfigService->set('foo.bar', 'override', Defaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertEquals('override', $actual);

        $this->systemConfigService->set('foo.bar', '', Defaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertEquals('', $actual);
    }

    public function testSetGetSalesChannelBool(): void
    {
        $this->systemConfigService->set('foo.bar', false);
        $actual = $this->systemConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertFalse($actual);

        $this->systemConfigService->set('foo.bar', true, Defaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo.bar', Defaults::SALES_CHANNEL);
        static::assertTrue($actual);
    }

    public function testGetDomainNoData(): void
    {
        $actual = $this->systemConfigService->getDomain('foo', null, false);
        static::assertEquals([], $actual);

        $actual = $this->systemConfigService->getDomain('foo', null, true);
        static::assertEquals([], $actual);

        $actual = $this->systemConfigService->getDomain('foo', Defaults::SALES_CHANNEL, false);
        static::assertEquals([], $actual);

        $actual = $this->systemConfigService->getDomain('foo', Defaults::SALES_CHANNEL, true);
        static::assertEquals([], $actual);
    }

    public function testGetDomain(): void
    {
        $this->systemConfigService->set('foo.a', 'a');
        $this->systemConfigService->set('foo.b', 'b');
        $this->systemConfigService->set('foo.c', 'c');
        $this->systemConfigService->set('foo.c', 'c override', Defaults::SALES_CHANNEL);

        $expected = [
            'foo.a' => 'a',
            'foo.b' => 'b',
            'foo.c' => 'c',
        ];
        $actual = $this->systemConfigService->getDomain('foo');
        static::assertEquals($expected, $actual);

        $expected = [
            'foo.a' => 'a',
            'foo.b' => 'b',
            'foo.c' => 'c override',
        ];
        $actual = $this->systemConfigService->getDomain('foo', Defaults::SALES_CHANNEL, true);
        static::assertEquals($expected, $actual);

        $expected = [
            'foo.c' => 'c override',
        ];
        $actual = $this->systemConfigService->getDomain('foo', Defaults::SALES_CHANNEL, false);
        static::assertEquals($expected, $actual);
    }

    public function testGetDomainInherit(): void
    {
        $this->systemConfigService->set('foo.bar', 'test');
        $this->systemConfigService->set('foo.bar', 'override', Defaults::SALES_CHANNEL);
        $this->systemConfigService->set('foo.bar', '', Defaults::SALES_CHANNEL);

        $expected = ['foo.bar' => 'test'];
        $actual = $this->systemConfigService->getDomain('foo', Defaults::SALES_CHANNEL, true);

        static::assertEquals($expected, $actual);
    }

    public function testGetDomainWithDots(): void
    {
        $this->systemConfigService->set('foo.a', 'a');
        $actual = $this->systemConfigService->getDomain('foo.');
        static::assertEquals(['foo.a' => 'a'], $actual);
    }

    public function testDeleteNonExisting(): void
    {
        $this->systemConfigService->delete('not.found', null);
        $this->systemConfigService->delete('not.found', Defaults::SALES_CHANNEL);

        // does not throw
        static::assertTrue(true);
    }

    public function testDelete(): void
    {
        $this->systemConfigService->set('foo', 'bar', null);
        $this->systemConfigService->set('foo', 'bar override', Defaults::SALES_CHANNEL);

        $this->systemConfigService->delete('foo', null);
        $actual = $this->systemConfigService->get('foo', null);
        static::assertNull($actual);
        $actual = $this->systemConfigService->get('foo', Defaults::SALES_CHANNEL);
        static::assertEquals('bar override', $actual);

        $this->systemConfigService->delete('foo', Defaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo', Defaults::SALES_CHANNEL);
        static::assertNull($actual);
    }

    public function testGetDomainEmptyThrows(): void
    {
        $this->expectException(InvalidDomainException::class);
        $this->systemConfigService->getDomain('');
    }

    public function testGetDomainOnlySpacesThrows(): void
    {
        $this->expectException(InvalidDomainException::class);
        $this->systemConfigService->getDomain('     ');
    }

    public function testSetEmptyKeyThrows(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->systemConfigService->set('', 'throws error');
    }

    public function testSetOnlySpacesKeyThrows(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->systemConfigService->set('          ', 'throws error');
    }

    public function testSetInvalidSalesChannelThrows(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->systemConfigService->set('foo.bar', 'test', 'invalid uuid');
    }
}
