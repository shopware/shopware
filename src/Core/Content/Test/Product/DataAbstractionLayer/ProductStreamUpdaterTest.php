<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductStreamUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $productRepository;

    private EntityRepositoryInterface $productStreamRepository;

    private SalesChannelContext $salesChannel;

    private ProductStreamUpdater $productStreamUpdater;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');
        $this->salesChannel = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->productStreamUpdater = $this->getContainer()->get(ProductStreamUpdater::class);
    }

    public function testIndexingDoesNotBreakOnInvalidProductStreamFilters(): void
    {
        $stream = [
            'name' => 'test',
            'filters' => [[
                'type' => 'equals',
                'field' => 'doesNotExist',
                'value' => '100',
            ]],
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $productStreamIndexer->handle(
            $productStreamIndexer->update($writtenEvent)
        );

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        // If this call does not throw an exception, everything is ok
        $this->productStreamUpdater->updateProducts([$productId], Context::createDefaultContext());
    }

    private function createProduct(string $productId): void
    {
        $this->productRepository->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => $productId,
                    'stock' => 1,
                    'name' => 'Test',
                    'active' => true,
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 100,
                            'net' => 9, 'linked' => false,
                        ],
                    ],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'with id'],
                    'visibilities' => [
                        ['salesChannelId' => $this->salesChannel->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                    ],
                    'categories' => [
                        ['id' => Uuid::randomHex(), 'name' => 'Clothing'],
                    ],
                ],
            ],
            $this->salesChannel->getContext()
        );
    }
}
