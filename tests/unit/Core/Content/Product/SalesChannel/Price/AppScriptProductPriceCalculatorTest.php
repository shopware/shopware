<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Content\Product\SalesChannel\Price\AppScriptProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(AppScriptProductPriceCalculator::class)]
class AppScriptProductPriceCalculatorTest extends TestCase
{
    public function testHookWillBeExecuted(): void
    {
        $products = [
            new SalesChannelProductEntity(),
            new SalesChannelProductEntity(),
        ];

        $executor = $this->createMock(ScriptExecutor::class);
        $executor->expects(static::once())->method('execute');

        $decorated = $this->createMock(ProductPriceCalculator::class);
        $decorated->expects(static::once())->method('calculate')->with($products);

        $calculator = new AppScriptProductPriceCalculator($decorated, $executor, $this->createMock(ScriptPriceStubs::class));

        $calculator->calculate($products, $this->createMock(SalesChannelContext::class));
    }
}
