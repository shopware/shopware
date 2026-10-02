<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Capability\CoreSalesChannelType;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CoreSalesChannelType::class)]
class CoreSalesChannelTypeTest extends TestCase
{
    #[DataProvider('coreTypeProvider')]
    public function testOnlyStorefrontAndHeadlessAreTransactional(CoreSalesChannelType $coreType, bool $isTransactionalExpected): void
    {
        static::assertSame($isTransactionalExpected, $coreType->isTransactional());
    }

    /**
     * @return iterable<string, array{CoreSalesChannelType, bool}>
     */
    public static function coreTypeProvider(): iterable
    {
        yield 'storefront sells' => [CoreSalesChannelType::STOREFRONT, true];
        yield 'headless sells' => [CoreSalesChannelType::HEADLESS, true];
        yield 'product comparison exports a feed' => [CoreSalesChannelType::PRODUCT_COMPARISON, false];
        yield 'agentic commerce exports a feed' => [CoreSalesChannelType::AGENTIC_COMMERCE, false];
    }
}
