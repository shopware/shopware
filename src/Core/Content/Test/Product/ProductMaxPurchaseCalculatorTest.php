<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductMaxPurchaseCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class ProductMaxPurchaseCalculatorTest extends TestCase
{
    private ProductMaxPurchaseCalculator $service;

    protected function setUp(): void
    {
        parent::setUp();

        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getInt')->willReturn(10);
        $this->service = new ProductMaxPurchaseCalculator($configService);
    }

    /**
     * @dataProvider cases
     */
    public function testCalculate(array $entityData, int $expected): void
    {
        $entity = new PartialEntity();
        $entity->assign($entityData);

        static::assertSame($expected, $this->service->calculate($entity, $this->createMock(SalesChannelContext::class)));
    }

    public static function cases(): iterable
    {
        yield 'empty' => [
            [
            ],
            10,
        ];

        yield 'max_in_entity' => [
            [
                'maxPurchase' => 5,
            ],
            5,
        ];

        yield 'purchase_steps' => [
            [
                'maxPurchase' => 5,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
            ],
            4,
        ];

        yield 'available_stock without closeout' => [
            [
                'maxPurchase' => 5,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
                'availableStock' => 2,
                'isCloseout' => false,
            ],
            4,
        ];

        yield 'available_stock only when closeout' => [
            [
                'maxPurchase' => 5,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
                'availableStock' => 2,
                'isCloseout' => true,
            ],
            2,
        ];
    }
}
