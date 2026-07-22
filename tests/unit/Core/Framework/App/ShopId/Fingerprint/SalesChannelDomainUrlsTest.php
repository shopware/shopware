<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\Fingerprint\SalesChannelDomainUrls;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelDomainUrls::class)]
class SalesChannelDomainUrlsTest extends TestCase
{
    public function testIdentifier(): void
    {
        $fingerprint = new SalesChannelDomainUrls(static::createStub(Connection::class));

        static::assertSame('sales_channel_domain_urls', $fingerprint->getIdentifier());
    }

    public function testScore(): void
    {
        $fingerprint = new SalesChannelDomainUrls(static::createStub(Connection::class));

        static::assertSame(25, $fingerprint->getScore());
    }

    public function testTakesSalesChannelDomainUrlsHash(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($urls = ['foo', 'bar', 'baz']);

        $fingerprint = new SalesChannelDomainUrls($connection);

        static::assertSame(\hash('md5', implode('', $urls)), $fingerprint->getStamp());
    }
}
