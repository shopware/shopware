<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedSalesChannelContextFactory::class)]
class CachedSalesChannelContextFactoryTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCustomerSpecificOptionsAreNotCached(): void
    {
        $context = Generator::generateSalesChannelContext();
        $options = [SalesChannelContextService::CUSTOMER_ID => 'customer-id'];

        $inner = $this->createMock(SalesChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $factory = new CachedSalesChannelContextFactory($inner, $cache);

        static::assertSame($context, $factory->create('token', 'sales-channel-id', $options));
    }

    public function testFreshlyBuiltContextIsReturnedDirectly(): void
    {
        $context = Generator::generateSalesChannelContext();
        $options = [SalesChannelContextService::LANGUAGE_ID => 'language-id'];

        $inner = $this->createMock(SalesChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $storedValue = null;
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback) use (&$storedValue) {
                // the second call replays the stored payload like a warm cache pool would
                return $storedValue ??= $callback(static::createStub(ItemInterface::class));
            });

        $factory = new CachedSalesChannelContextFactory($inner, $cache);

        $first = $factory->create('token', 'sales-channel-id', $options);

        static::assertSame($context, $first, 'a context built in this call is returned without a serialization round trip');

        $second = $factory->create('other-token', 'sales-channel-id', $options);

        static::assertNotSame($context, $second, 'a cache hit is unserialized into a fresh instance');
        static::assertSame('other-token', $second->getToken());
        static::assertSame($context->getSalesChannelId(), $second->getSalesChannelId());
    }

    public function testBypassesCacheWhenAtsIsRunning(): void
    {
        $this->setEnvVars(['ATS_RUNNING' => '1']);
        $context = static::createStub(SalesChannelContext::class);
        $decorated = $this->createMock(AbstractSalesChannelContextFactory::class);
        $decorated->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', ['languageId' => 'language-id'])
            ->willReturn($context);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $factory = new CachedSalesChannelContextFactory($decorated, $cache);

        static::assertSame($context, $factory->create('token', 'sales-channel-id', ['languageId' => 'language-id']));
    }
}
