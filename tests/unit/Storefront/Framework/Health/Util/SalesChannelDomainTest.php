<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;

/**
 * @internal
 */
#[CoversClass(SalesChannelDomain::class)]
class SalesChannelDomainTest extends TestCase
{
    public function testCreate(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $url = 'http://localhost:8000';

        $domain = SalesChannelDomain::create($salesChannelId, $url);

        static::assertSame($salesChannelId, $domain->salesChannelId);
        static::assertSame($url, $domain->url);
    }
}
