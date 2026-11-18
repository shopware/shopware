<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\SalesChannelMaintenanceCacheTag;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelMaintenanceCacheTag::class)]
class SalesChannelMaintenanceCacheTagTest extends TestCase
{
    public function testBuildsTagForSalesChannel(): void
    {
        static::assertSame(
            'sales-channel-maintenance-sales-channel-id',
            SalesChannelMaintenanceCacheTag::build('sales-channel-id')
        );
    }
}
