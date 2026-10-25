<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDomain::class)]
class SalesChannelDomainTest extends TestCase
{
    public function testCreate(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $url = 'http://localhost:8000';

        $domain = SalesChannelDomain::create($salesChannelId, $url, 'domain-id', 'language-id', 'currency-id');

        static::assertSame($salesChannelId, $domain->salesChannelId);
        static::assertSame($url, $domain->url);
        // the readiness checks build their lookup context from these, so they travel with the URL
        static::assertSame('domain-id', $domain->id);
        static::assertSame('language-id', $domain->languageId);
        static::assertSame('currency-id', $domain->currencyId);
    }
}
