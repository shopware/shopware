<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SalesChannelProductSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMaxQuantityCalculation(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $defaults = [
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $cases = [
            new MaxPurchaseTestCase(10, false, 25, 10, 100, 'should use configured max purchase'),
            new MaxPurchaseTestCase(10, false, 1, 10, 100, 'less stock, but not closeout'),
            new MaxPurchaseTestCase(20, false, 5, null, 20, 'not configured, fallback to config'),

            new MaxPurchaseTestCase(2, true, 2, 10, 100, 'closeout with less stock'),
            new MaxPurchaseTestCase(10, true, 30, 10, 50, 'use configured max purchase for closeout with stock'),
            new MaxPurchaseTestCase(2, true, 2, null, 50, 'not configured, use stock because closeout'),
        ];

        foreach ($cases as $i => $case) {
            $id = $ids->create('product-' . $i);

            $data = array_merge($defaults, [
                'id' => $id,
                'productNumber' => $id,

                'maxPurchase' => $case->maxPurchase,
                'isCloseout' => $case->isCloseout,
                'stock' => $case->stock,
            ]);

            $this->getContainer()->get('product.repository')
                ->create([$data], $ids->getContext());

            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

            $this->getContainer()->get(SystemConfigService::class)
                ->set('core.cart.maxQuantity', $case->config);

            $product = $this->getContainer()->get('sales_channel.product.repository')
                ->search(new Criteria([$id]), $context)
                ->get($id);

            static::assertInstanceOf(SalesChannelProductEntity::class, $product);

            /** @var SalesChannelProductEntity $product */
            static::assertSame($case->expected, $product->getCalculatedMaxPurchase(), $case->description);
        }
    }
}

class MaxPurchaseTestCase
{
    /**
     * @var bool
     */
    public $isCloseout;

    /**
     * @var int
     */
    public $stock;

    /**
     * @var int|null
     */
    public $maxPurchase;

    /**
     * @var int
     */
    public $expected;

    /**
     * @var int
     */
    public $config;

    /**
     * @var string
     */
    public $description;

    public function __construct(int $expected, bool $isCloseout, int $stock, ?int $maxPurchase, int $config, string $description)
    {
        $this->isCloseout = $isCloseout;
        $this->stock = $stock;
        $this->maxPurchase = $maxPurchase;
        $this->expected = $expected;
        $this->config = $config;
        $this->description = $description;
    }
}
