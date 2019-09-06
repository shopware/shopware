<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Product\Configurator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;

class ProductCombinationFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var string
     */
    private $productId;

    /**
     * @var string
     */
    private $salesChannelId;

    private $optionIds = [];

    private $groupIds = [];

    private $variantIds = [];

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var ProductCombinationFinder
     */
    private $combinationFinder;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', Defaults::SALES_CHANNEL);

        $this->combinationFinder = $this->getContainer()->get(ProductCombinationFinder::class);

        $this->createProduct();

        parent::setUp();
    }

    public function testSwitchVariant(): void
    {
        $options = [
            $this->groupIds['color'] => $this->optionIds['red'],
            $this->groupIds['size'] => $this->optionIds['xl'],
        ];

        $switched = $this->groupIds['color'];

        $result = $this->combinationFinder->find($this->productId, $switched, $options, $this->context);

        static::assertEquals($this->variantIds['redXl'], $result->getVariantId());
    }

    public function testSwitchToNotCombinable(): void
    {
        //update red-xl to inactive
        $this->repository->update(
            [
                ['id' => $this->variantIds['redXl'], 'active' => false],
            ],
            Context::createDefaultContext()
        );

        $switched = $this->groupIds['color'];

        $options = [
            $this->groupIds['color'] => $this->optionIds['red'],
            $this->groupIds['size'] => $this->optionIds['xl'],
        ];

        // wished to switch to red-xl but this variant is not available (active = false).
        // should switch to next matching size
        $result = $this->combinationFinder->find($this->productId, $switched, $options, $this->context);

        static::assertEquals($this->variantIds['redL'], $result->getVariantId());
    }

    private function createProduct(): void
    {
        $this->productId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXl' => Uuid::randomHex(),
            'greenXl' => Uuid::randomHex(),
            'redL' => Uuid::randomHex(),
            'greenL' => Uuid::randomHex(),
        ];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
        ];

        $data = [
            [
                'id' => $this->productId,
                'name' => 'Test product',
                'productNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $this->optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['xl'],
                            'name' => 'XL',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['l'],
                            'name' => 'L',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->variantIds['redXl'],
                'productNumber' => 'a.1',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXl'],
                'productNumber' => 'a.2',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['redL'],
                'productNumber' => 'a.3',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
            [
                'id' => $this->variantIds['greenL'],
                'productNumber' => 'a.4',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());
    }
}
