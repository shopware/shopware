<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\Fingerprint\SalesChannelDomainUrls;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

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
            ->willReturn(['foo', 'bar', 'baz']);

        $fingerprint = new SalesChannelDomainUrls($connection);

        static::assertSame(\hash('md5', 'barbazfoo'), $fingerprint->getStamp());
    }

    public function testStampDoesNotDependOnTheOrderOfTheDomainUrls(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls(
            ['https://a.example/de', 'https://b.example/de', 'https://c.example/en'],
            ['https://c.example/en', 'https://a.example/de', 'https://b.example/de'],
        );

        $fingerprint = new SalesChannelDomainUrls($connection);

        static::assertSame($fingerprint->getStamp(), $fingerprint->getStamp());
    }

    public function testCompareMatchesTheSortedStamp(): void
    {
        $fingerprint = new SalesChannelDomainUrls($this->createConnection(['foo', 'bar', 'baz']));

        static::assertSame(0, $fingerprint->compare(\hash('md5', 'barbazfoo')));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareMatchesAStampTakenBeforeSorting(): void
    {
        $fingerprint = new SalesChannelDomainUrls($this->createConnection(['foo', 'bar', 'baz']));

        static::assertSame(0, $fingerprint->compare(\hash('md5', 'foobarbaz')));
    }

    public function testCompareDoesNotMatchAStampTakenBeforeSortingFromV68(): void
    {
        $fingerprint = new SalesChannelDomainUrls($this->createConnection(['foo', 'bar', 'baz']));

        static::assertSame(25, $fingerprint->compare(\hash('md5', 'foobarbaz')));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareDoesNotMatchAMissingOrDifferentStamp(): void
    {
        $fingerprint = new SalesChannelDomainUrls($this->createConnection(['foo', 'bar', 'baz']));

        static::assertSame(25, $fingerprint->compare(null));
        static::assertSame(25, $fingerprint->compare(\hash('md5', 'barbaz')));
    }

    /**
     * @param list<string> $urls
     */
    private function createConnection(array $urls): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($urls);

        return $connection;
    }
}
