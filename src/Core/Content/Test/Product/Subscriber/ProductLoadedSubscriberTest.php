<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\Subscriber\ProductSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class ProductLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
    }

    public function testExtensionSubscribesToProductLoaded(): void
    {
        static::assertArrayHasKey(ProductEvents::PRODUCT_LOADED_EVENT, ProductSubscriber::getSubscribedEvents());
        static::assertCount(1, ProductSubscriber::getSubscribedEvents()[ProductEvents::PRODUCT_LOADED_EVENT]);
    }

    /**
     * @dataProvider propertyCases
     */
    public function testSortProperties(array $product, array $expected, array $unexpected, Criteria $criteria): void
    {
        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

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
                    'salesChannelId' => Defaults::SALES_CHANNEL,
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
}
