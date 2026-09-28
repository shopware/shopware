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

        yield 'digital product allows a configured maxPurchase above 1' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
                'maxPurchase' => 5,
            ],
            5,
        ];

        yield 'digital product with a maxPurchase above 1 is still limited by closeout stock' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
                'maxPurchase' => 5,
                'stock' => 2,
                'isCloseout' => true,
            ],
            2,
        ];

        yield 'digital product caps max at 1 even when maxPurchase is null' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
            ],
            1,
        ];

        yield 'digital product caps max at 1 instead of honouring a maxPurchase of 0' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
                'maxPurchase' => 0,
            ],
            1,
        ];

        yield 'digital product without maxPurchase is out of stock when closeout stock is empty' => [
            [
                'type' => ProductDefinition::TYPE_DIGITAL,
                'stock' => 0,
                'isCloseout' => true,
            ],
            0,
        ];

        yield 'non-digital product with null maxPurchase falls back to system config' => [
            [
                'type' => ProductDefinition::TYPE_PHYSICAL,
            ],
            10,
        ];
    }

    /**
     * @param array<string, int|list<string>> $entityData
     */
    #[DataProvider('legacyDownloadStateCases')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCalculateWithLegacyDownloadStateWhile68IsInactive(array $entityData, int $expected): void
    {
        $entity = new PartialEntity();
        $entity->assign($entityData);

        static::assertSame($expected, $this->service->calculate($entity, static::createStub(SalesChannelContext::class)));
    }

    public static function legacyDownloadStateCases(): \Generator
    {
        yield 'download state caps max at 1 when maxPurchase is null' => [
            [
                'states' => [State::IS_DOWNLOAD],
            ],
            1,
        ];

        yield 'download state allows a configured maxPurchase above 1' => [
            [
                'maxPurchase' => 5,
                'states' => [State::IS_DOWNLOAD],
            ],
            5,
        ];

        yield 'other states fall back to system config instead of the digital cap' => [
            [
                'states' => ['some-other-state'],
            ],
            10,
        ];
    }
}
