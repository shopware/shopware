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
        $product1 = new SalesChannelProductEntity();
        $product1->setId('product-1');
        $product2 = new SalesChannelProductEntity();
        $product2->setId('product-2');

        $products = [
            $product1,
            $product2,
        ];

        $executor = $this->createMock(ScriptExecutor::class);
        $executor->expects($this->once())->method('execute');

        $decorated = $this->createMock(ProductPriceCalculator::class);
        $decorated->expects($this->once())->method('calculate')->with($products);

        $calculator = new AppScriptProductPriceCalculator($decorated, $executor, $this->createMock(ScriptPriceStubs::class));

        $calculator->calculate($products, $this->createMock(SalesChannelContext::class));
    }
}
