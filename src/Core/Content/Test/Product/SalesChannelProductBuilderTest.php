<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannelProductBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

class SalesChannelProductBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider maxPurchaseProvider
     */
    public function testMaxPurchaseCalculation(int $expected, bool $closeout, int $stock, int $steps, ?int $max, int $config): void
    {
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.cart.maxQuantity', $config);

        $product = new SalesChannelProductEntity();
        $product->setIsCloseout($closeout);

        if ($max) {
            $product->setMaxPurchase($max);
        }

        $product->setAvailableStock($stock);
        $product->setPurchaseSteps($steps);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $salesChannelProductBuilder = $this->getContainer()->get(SalesChannelProductBuilder::class);
        $salesChannelProductBuilder->build($product, $context);

        static::assertSame($expected, $product->getCalculatedMaxPurchase());
    }

    public function maxPurchaseProvider()
    {
        // expected, closeout, stock, steps, max, config
        yield 'should use configured max purchase' => [10, false, 25, 1, 10, 100];
        yield 'less stock, but not closeout' => [10, false, 1, 1, 10, 100];
        yield 'not configured, fallback to config' => [20, false, 5, 1, null, 20];
        yield 'closeout with less stock' => [2, true, 2, 1, 10, 100];
        yield 'use configured max purchase for closeout with stock' => [10, true, 30, 1, 10, 50];
        yield 'not configured, use stock because closeout' => [2, true, 2, 1, null, 50];
        yield 'next step would be higher than available' => [7, true, 9, 6, 20, 20];
        yield 'second step would be higher than available' => [13, true, 13, 6, 20, 20];
        yield 'max config is not in steps' => [13, true, 100, 12, 22, 22];
        yield 'max config is last step' => [15, false, 100, 2, 15, 15];
    }
}
