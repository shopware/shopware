<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Shopware\Core\System\SalesChannel\Context\AbstractBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedBaseSalesChannelContextFactory::class)]
class CachedBaseSalesChannelContextFactoryTest extends TestCase
{
    use EnvTestBehaviour;

    public function testBypassesCacheWhenAtsIsRunning(): void
    {
        $this->setEnvVars(['ATS_RUNNING' => '1']);
        $context = static::createStub(BaseSalesChannelContext::class);
        $decorated = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $decorated->expects($this->once())
            ->method('create')
            ->with('sales-channel-id', ['languageId' => 'language-id'])
            ->willReturn($context);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $factory = new CachedBaseSalesChannelContextFactory($decorated, $cache);

        static::assertSame($context, $factory->create('sales-channel-id', ['languageId' => 'language-id']));
    }
}
