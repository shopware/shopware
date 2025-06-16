<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainCollection;

/**
 * @internal
 */
#[CoversClass(SalesChannelDomainCollection::class)]
class SalesChannelDomainCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $domain1 = SalesChannelDomain::create('test-sales-channel-id-1', 'http://localhost:8000');
        $domain2 = SalesChannelDomain::create('test-sales-channel-id-2', 'http://localhost:8001');

        $collection = new SalesChannelDomainCollection([$domain1, $domain2]);

        static::assertCount(2, $collection);

        $domain = $collection->get('test-sales-channel-id-1');
        static::assertInstanceOf(SalesChannelDomain::class, $domain);
        static::assertSame('http://localhost:8000', $domain->url);

        $domain = $collection->get('test-sales-channel-id-2');
        static::assertInstanceOf(SalesChannelDomain::class, $domain);
        static::assertSame('http://localhost:8001', $domain->url);
    }
}
