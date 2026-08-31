<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductMaxPurchaseCalculator::class)]
class ProductMaxPurchaseCalculatorTest extends TestCase
{
    private ProductMaxPurchaseCalculator $service;

    protected function setUp(): void
    {
        parent::setUp();

        $configService = static::createStub(SystemConfigService::class);
        $configService->method('getInt')->willReturn(10);
        $this->service = new ProductMaxPurchaseCalculator($configService);
    }

    /**
     * @param array<string, int|bool|string> $entityData
     */
    #[DataProvider('cases')]
    public function testCalculate(array $entityData, int $expected): void
    {
        $entity = new PartialEntity();
        $entity->assign($entityData);

        static::assertSame($expected, $this->service->calculate($entity, static::createStub(SalesChannelContext::class)));
    }

    public static function cases(): \Generator
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
                'stock' => 2,
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
                'stock' => 2,
                'isCloseout' => true,
            ],
            2,
        ];

        yield 'digital product caps max at 1 regardless of configured maxPurchase' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
                'maxPurchase' => 5,
            ],
            1,
        ];

        yield 'digital product caps max at 1 even when maxPurchase is null' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
            ],
            1,
        ];

        yield 'non-digital product with null maxPurchase falls back to system config' => [
            [
                'type' => ProductDefinition::TYPE_PHYSICAL,
            ],
            10,
        ];
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyDownloadStateCapsMaxAtOneWhile68IsInactive(): void
    {
        $entity = new PartialEntity();
        $entity->assign([
            'maxPurchase' => 5,
            'states' => [State::IS_DOWNLOAD],
        ]);

        static::assertSame(
            1,
            $this->service->calculate($entity, static::createStub(SalesChannelContext::class)),
            'legacy IS_DOWNLOAD state must cap quantity to 1 while v6.8.0.0 is inactive'
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testNonDigitalProductWithoutDownloadStateSkipsLegacyFallbackWhile68IsInactive(): void
    {
        $entity = new PartialEntity();
        $entity->assign([
            'maxPurchase' => 5,
            'states' => ['some-other-state'],
        ]);

        static::assertSame(
            5,
            $this->service->calculate($entity, static::createStub(SalesChannelContext::class)),
            'non-download product must not be capped by the legacy state check'
        );
    }
}
