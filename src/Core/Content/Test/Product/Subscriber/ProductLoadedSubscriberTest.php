<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\Subscriber\ProductSubscriber;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

class ProductLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
    }

    public function testExtensionSubscribesToProductLoaded(): void
    {
        static::assertArrayHasKey(ProductEvents::PRODUCT_LOADED_EVENT, ProductSubscriber::getSubscribedEvents());
        static::assertArrayHasKey('sales_channel.product.loaded', ProductSubscriber::getSubscribedEvents());
        static::assertIsString(ProductSubscriber::getSubscribedEvents()[ProductEvents::PRODUCT_LOADED_EVENT]);
        static::assertIsString(ProductSubscriber::getSubscribedEvents()['sales_channel.product.loaded']);
    }

    public function testCheapestPriceOnSalesChannelProductEntity(): void
    {
        $ids = new IdsCollection();

        $this->getContainer()->get('product.repository')
            ->create([
                (new ProductBuilder($ids, 'p.1'))
                    ->price(130)
                    ->prices('rule-a', 150)
                    ->visibility()
                    ->build(),
            ], Context::createDefaultContext());

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        /** @var SalesChannelProductEntity $productEntity */
        $productEntity = $this->getContainer()
            ->get('sales_channel.product.repository')
            ->search(new Criteria([$ids->get('p.1')]), $salesChannelContext)
            ->first();

        static::assertInstanceOf(CheapestPrice::class, $productEntity->getCheapestPrice());
    }

    /**
     * @dataProvider propertyCases
     */
    public function testSortProperties(array $product, array $expected, array $unexpected, Criteria $criteria): void
    {
        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $criteria->setIds([$product['id']])
            ->addAssociation('properties.group');

        /** @var SalesChannelProductEntity $productEntity */
        $productEntity = $this->getContainer()
            ->get('sales_channel.product.repository')
            ->search($criteria, $salesChannelContext)
            ->first();

        $subscriber = $this->getContainer()->get(ProductSubscriber::class);
        $productLoadedEvent = new EntityLoadedEvent(
            $this->getContainer()->get(ProductDefinition::class),
            [$productEntity],
            Context::createDefaultContext()
        );
        $subscriber->loaded($productLoadedEvent);

        $sortedProperties = $productEntity->getSortedProperties()->getElements();

        foreach ($expected as $expectedGroupKey => $expectedGroup) {
            $optionElements = $sortedProperties[$expectedGroupKey]->getOptions()->getElements();

            static::assertEquals($expectedGroup['name'], $sortedProperties[$expectedGroupKey]->getName());
            static::assertEquals($expectedGroup['id'], $sortedProperties[$expectedGroupKey]->getId());
            static::assertEquals(array_keys($expectedGroup['options']), array_keys($optionElements));

            foreach ($expectedGroup['options'] as $optionId => $option) {
                static::assertEquals($option['id'], $optionElements[$optionId]->getId());
                static::assertEquals($option['name'], $optionElements[$optionId]->getName());
            }
        }

        foreach ($unexpected as $unexpectedGroup) {
            static::assertArrayNotHasKey($unexpectedGroup['id'], $sortedProperties);
        }
    }

    public function propertyCases(): array
    {
        $ids = new TestDataCollection();

        $defaults = [
            'id' => $ids->get('product'),
            'name' => 'test-product',
            'productNumber' => $ids->get('product'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        return [
            [
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('bitter'),
                            'name' => 'bitter',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('sweet'),
                            'name' => 'sweet',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('hiddenValue'),
                            'name' => 'hiddenValue',
                            'groupId' => $ids->get('hidden'),
                            'group' => ['id' => $ids->get('hidden'), 'name' => 'hidden', 'visibleOnProductDetailPage' => false],
                        ],
                    ],
                ]),
                [
                    [
                        'id' => $ids->get('taste'),
                        'name' => 'taste',
                        'options' => [
                            $ids->get('bitter') => [
                                'id' => $ids->get('bitter'),
                                'name' => 'bitter',
                            ],
                            $ids->get('sweet') => [
                                'id' => $ids->get('sweet'),
                                'name' => 'sweet',
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'id' => $ids->get('hidden'),
                        'name' => 'hidden',
                        'visibleOnProductDetailPage' => false,
                        'options' => [
                            $ids->get('hiddenValue') => [
                                'id' => $ids->get('hiddenValue'),
                                'name' => 'hiddenValue',
                            ],
                        ],
                    ],
                ],
                (new Criteria()),
            ],
            [
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('bitter'),
                            'name' => 'bitter',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('sweet'),
                            'name' => 'sweet',
                            'groupId' => $ids->get('taste'),
                            'group' => ['id' => $ids->get('taste'), 'name' => 'taste'],
                        ],
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('leather'),
                            'name' => 'leather',
                            'groupId' => $ids->get('material'),
                            'group' => ['id' => $ids->get('material'), 'name' => 'material'],
                        ],
                    ],
                ]),
                [
                    [
                        'id' => $ids->get('color'),
                        'name' => 'color',
                        'options' => [
                            $ids->get('red') => [
                                'id' => $ids->get('red'),
                                'name' => 'red',
                            ],
                        ],
                    ],
                    [
                        'id' => $ids->get('material'),
                        'name' => 'material',
                        'options' => [
                            $ids->get('leather') => [
                                'id' => $ids->get('leather'),
                                'name' => 'leather',
                            ],
                        ],
                    ],
                    [
                        'id' => $ids->get('taste'),
                        'name' => 'taste',
                        'options' => [
                            $ids->get('bitter') => [
                                'id' => $ids->get('bitter'),
                                'name' => 'bitter',
                            ],
                            $ids->get('sweet') => [
                                'id' => $ids->get('sweet'),
                                'name' => 'sweet',
                            ],
                        ],
                    ],
                ],
                [],
                (new Criteria()),
            ],
        ];
    }

    /**
     * @dataProvider variationCases
     */
    public function testVariation(array $product, $expected, array $languageChain, Criteria $criteria, bool $sort, array $language): void
    {
        $this->getContainer()
            ->get('language.repository')
            ->create([$language], Context::createDefaultContext());

        $productId = $product['id'];
        $context = Context::createDefaultContext();

        $this->getContainer()->get('product.repository')
            ->create([$product], $context);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            $languageChain
        );

        $criteria->setIds([$productId]);

        $productEntity = $this->getContainer()
            ->get('product.repository')
            ->search($criteria, $context)
            ->first();

        $subscriber = $this->getContainer()->get(ProductSubscriber::class);
        $productLoadedEvent = new EntityLoadedEvent($this->getContainer()->get(ProductDefinition::class), [$productEntity], $context);
        $subscriber->loaded($productLoadedEvent);

        $variation = $productEntity->getVariation();

        if ($sort) {
            sort($variation);
            sort($expected);
        }

        static::assertEquals($expected, $variation);
    }

    public function variationCases(): array
    {
        $ids = new TestDataCollection();

        $defaults = [
            'id' => $ids->get('product'),
            'name' => 'test-product',
            'productNumber' => $ids->get('product'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $language = [
            'id' => $ids->create('language'),
            'name' => 'sub_en',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
        ];

        return [
            0 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria()),
                false,
                $language,
            ],
            1 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'size', 'option' => 'xl'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                ],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $language,
            ],
            2 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    $this->getDeDeLanguageId() => ['name' => 'farbe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $this->getDeDeLanguageId() => ['name' => 'rot'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    $this->getDeDeLanguageId() => ['name' => 'größe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $this->getDeDeLanguageId() => ['name' => 'extra gross'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    $this->getDeDeLanguageId() => ['name' => 'passform'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $this->getDeDeLanguageId() => ['name' => 'schmal'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'farbe', 'option' => 'rot'],
                    ['group' => 'größe', 'option' => 'extra gross'],
                    ['group' => 'passform', 'option' => 'schmal'],
                ],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $language,
            ],
            3 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'size', 'option' => 'xl'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                ],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $language,
            ],
            4 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'size', 'option' => 'xl'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                ],
                [$ids->get('language'), $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $language,
            ],
            5 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    $this->getDeDeLanguageId() => ['name' => 'farbe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $ids->get('language') => ['name' => 'der'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    $this->getDeDeLanguageId() => ['name' => 'größe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $ids->get('language') => ['name' => 'lx'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    $this->getDeDeLanguageId() => ['name' => 'passform'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $ids->get('language') => ['name' => 'tif mils'],
                            ],
                        ],
                    ],
                ]),
                [
                    ['group' => 'farbe', 'option' => 'der'],
                    ['group' => 'größe', 'option' => 'lx'],
                    ['group' => 'passform', 'option' => 'tif mils'],
                ],
                [$ids->get('language'), $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                true,
                $language,
            ],
            6 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $language,
            ],
            7 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    $this->getDeDeLanguageId() => ['name' => 'farbe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $this->getDeDeLanguageId() => ['name' => 'rot'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    $this->getDeDeLanguageId() => ['name' => 'größe'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $this->getDeDeLanguageId() => ['name' => 'extra gross'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    $this->getDeDeLanguageId() => ['name' => 'passform'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $this->getDeDeLanguageId() => ['name' => 'schmal'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'farbe', 'option' => 'rot'],
                    ['group' => 'größe', 'option' => 'extra gross'],
                    ['group' => 'passform', 'option' => 'schmal'],
                ],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $language,
            ],
            8 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $language,
            ],
            9 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [$ids->get('language'), $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $language,
            ],
            10 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => [
                                'id' => $ids->get('color'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'color'],
                                    $ids->get('language') => ['name' => 'foo'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $ids->get('language') => ['name' => 'der'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => [
                                'id' => $ids->get('size'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'size'],
                                    $ids->get('language') => ['name' => 'bar'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $ids->get('language') => ['name' => 'lx'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => [
                                'id' => $ids->get('fit'),
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => ['name' => 'fit'],
                                    $ids->get('language') => ['name' => 'baz'],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $ids->get('language') => ['name' => 'tif mils'],
                            ],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'bar', 'option' => 'lx'],
                    ['group' => 'baz', 'option' => 'tif mils'],
                    ['group' => 'foo', 'option' => 'der'],
                ],
                [$ids->get('language'), $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $language,
            ],
            11 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                    'configuratorGroupConfig' => [
                        [
                            'id' => $ids->get('fit'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('color'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                        [
                            'id' => $ids->get('size'),
                            'representation' => 'box',
                            'expressionForListings' => true,
                        ],
                    ],
                ]),
                [
                    ['group' => 'color', 'option' => 'red'],
                    ['group' => 'fit', 'option' => 'slim fit'],
                    ['group' => 'size', 'option' => 'xl'],
                ],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options.group'),
                false,
                $language,
            ],
        ];
    }

    /**
     * @dataProvider optionCases
     */
    public function testOptionSorting(array $product, $expected, Criteria $criteria, array $language): void
    {
        $this->getContainer()
            ->get('language.repository')
            ->create([$language], Context::createDefaultContext());

        $productId = $product['id'];
        $context = Context::createDefaultContext();

        $this->getContainer()->get('product.repository')
            ->create([$product], $context);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $criteria->setIds([$productId]);

        /** @var ProductEntity $productEntity */
        $productEntity = $this->getContainer()
            ->get('product.repository')
            ->search($criteria, $context)
            ->first();

        $options = $productEntity->getOptions();
        $names = $options->map(function (PropertyGroupOptionEntity $option) {
            return [
                'name' => $option->getName(),
            ];
        });

        static::assertEquals($expected, array_values($names));
    }

    public function optionCases(): array
    {
        $ids = new TestDataCollection();

        $defaults = [
            'id' => $ids->get('product'),
            'name' => 'test-product',
            'productNumber' => $ids->get('product'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $language = [
            'id' => $ids->create('language'),
            'name' => 'sub_en',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
        ];

        $optionsAscCriteria = (new Criteria())->addAssociation('options.group');
        $optionsAscCriteria->getAssociation('options')->addSorting(new FieldSorting('name', 'ASC'));

        $optionsDescCriteria = (new Criteria())->addAssociation('options.group');
        $optionsDescCriteria->getAssociation('options')->addSorting(new FieldSorting('name', 'DESC'));

        return [
            1 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [
                    ['name' => 'red'],
                    ['name' => 'slim fit'],
                    ['name' => 'xl'],
                ],
                $optionsAscCriteria,
                $language,
            ],
            2 => [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'name' => 'xl',
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'name' => 'slim fit',
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                        ],
                    ],
                ]),
                [
                    ['name' => 'xl'],
                    ['name' => 'slim fit'],
                    ['name' => 'red'],
                ],
                $optionsDescCriteria,
                $language,
            ],
        ];
    }

    public function testListPrices(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $taxId = $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM tax LIMIT 1');

        $this->getContainer()->get('currency.repository')
            ->create([
                [
                    'id' => $ids->create('currency'),
                    'name' => 'test',
                    'shortName' => 'test',
                    'factor' => 1.5,
                    'symbol' => 'XXX',
                    'isoCode' => 'XX',
                    'decimalPrecision' => 3,
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(3, 0.01, true)), true),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(3, 0.01, true)), true),
                ],
            ], $ids->context);

        $defaults = [
            'id' => 1,
            'name' => 'test',
            'stock' => 10,
            'taxId' => $taxId,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $cases = [
            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -100, 100, 200),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -45, 90, 135),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -45, 90, 135),

            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -100, 100, 200, $ids->get('currency'), $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -45, 90, 135, $ids->get('currency'), $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -45, 90, 135, $ids->get('currency'), $ids->get('currency')),

            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -150, 150, 300, Defaults::CURRENCY, $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -67.5, 135, 202.5, Defaults::CURRENCY, $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -67.5, 135, 202.5, Defaults::CURRENCY, $ids->get('currency')),
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        foreach ($cases as $i => $case) {
            // prepare currency factor calculation
            $factor = 1;
            if ($case->usedCurrency !== Defaults::CURRENCY) {
                $factor = 1.5;
            }

            $context->getContext()->assign(['currencyFactor' => $factor]);
            $context->getCurrency()->setId($case->usedCurrency);

            // test different tax states
            $context->setTaxState($case->taxState);

            // create a new product for this case
            $id = $ids->create('product-' . $i);

            $price = [
                [
                    'currencyId' => $case->currencyId,
                    'gross' => $case->gross,
                    'net' => $case->net,
                    'linked' => false,
                    'listPrice' => [
                        'gross' => $case->wasGross,
                        'net' => $case->wasNet,
                        'linked' => false,
                    ],
                ],
            ];
            if ($case->currencyId !== Defaults::CURRENCY) {
                $price[] = [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 1,
                    'net' => 1,
                    'linked' => false,
                ];
            }

            $data = array_merge($defaults, [
                'id' => $id,
                'productNumber' => $id,
                'price' => $price,
            ]);

            $this->getContainer()->get('product.repository')
                ->create([$data], $ids->context);

            $product = $this->getContainer()->get('sales_channel.product.repository')
                ->search(new Criteria([$id]), $context)
                ->get($id);

            static::assertInstanceOf(SalesChannelProductEntity::class, $product);

            $price = $product->getCalculatedPrice();

            static::assertInstanceOf(ListPrice::class, $price->getListPrice());

            static::assertEquals($case->expectedPrice, $price->getUnitPrice());
            static::assertEquals($case->expectedWas, $price->getListPrice()->getPrice());

            static::assertEquals($case->percentage, $price->getListPrice()->getPercentage());
            static::assertEquals($case->discount, $price->getListPrice()->getDiscount());
        }
    }
}

class ListPriceTestCase
{
    /**
     * @var float
     */
    public $gross;

    /**
     * @var float
     */
    public $net;

    /**
     * @var float
     */
    public $wasGross;

    /**
     * @var float
     */
    public $wasNet;

    /**
     * @var string
     */
    public $currencyId;

    /**
     * @var float
     */
    public $percentage;

    /**
     * @var string
     */
    public $taxState;

    /**
     * @var float
     */
    public $discount;

    /**
     * @var string
     */
    public $usedCurrency;

    /**
     * @var float
     */
    public $expectedPrice;

    /**
     * @var float
     */
    public $expectedWas;

    public function __construct(
        float $gross,
        float $net,
        float $wasGross,
        float $wasNet,
        float $percentage,
        string $taxState,
        float $discount,
        float $expectedPrice,
        float $expectedWas,
        string $currencyId = Defaults::CURRENCY,
        string $usedCurrency = Defaults::CURRENCY
    ) {
        $this->gross = $gross;
        $this->net = $net;
        $this->wasGross = $wasGross;
        $this->wasNet = $wasNet;
        $this->currencyId = $currencyId;
        $this->percentage = $percentage;
        $this->taxState = $taxState;
        $this->discount = $discount;
        $this->usedCurrency = $usedCurrency;
        $this->expectedPrice = $expectedPrice;
        $this->expectedWas = $expectedWas;
    }
}
