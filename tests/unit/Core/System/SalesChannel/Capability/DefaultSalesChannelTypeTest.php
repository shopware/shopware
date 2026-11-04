<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Capability\DefaultSalesChannelType;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DefaultSalesChannelType::class)]
class DefaultSalesChannelTypeTest extends TestCase
{
    #[DataProvider('defaultTypeProvider')]
    public function testOnlyStorefrontAndHeadlessAreTransactional(DefaultSalesChannelType $salesChannelType, bool $shouldBeTransactional): void
    {
        static::assertSame($shouldBeTransactional, $salesChannelType->isTransactional());
    }

    /**
     * @return iterable<string, array{DefaultSalesChannelType, bool}>
     */
    public static function defaultTypeProvider(): iterable
    {
        yield 'storefront sells' => [DefaultSalesChannelType::STOREFRONT, true];
        yield 'headless sells' => [DefaultSalesChannelType::HEADLESS, true];
        yield 'product comparison exports a feed' => [DefaultSalesChannelType::PRODUCT_COMPARISON, false];
    }
}
