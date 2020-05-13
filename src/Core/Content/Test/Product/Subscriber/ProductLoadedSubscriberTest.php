<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\Subscriber\ProductLoadedSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext7399;

class ProductLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        skipTestNext7399($this);
    }

    public function testExtensionSubscribesToProductLoaded(): void
    {
        static::assertArrayHasKey(ProductEvents::PRODUCT_LOADED_EVENT, ProductLoadedSubscriber::getSubscribedEvents());
        static::assertCount(1, ProductLoadedSubscriber::getSubscribedEvents()[ProductEvents::PRODUCT_LOADED_EVENT]);
    }

    /**
     * @dataProvider variantCharacteristicsCases
     */
    public function testVariantCharacteristics(array $product, $expected, array $languageChain, Criteria $criteria, bool $sort): void
    {
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

        $subscriber = $this->getContainer()->get(ProductLoadedSubscriber::class);
        $productLoadedEvent = new EntityLoadedEvent($this->getContainer()->get(ProductDefinition::class), [$productEntity], $context);
        $subscriber->addVariantCharacteristics($productLoadedEvent);

        $characteristics = $productEntity->getVariantCharacteristics();

        if ($sort) {
            sort($characteristics);
            sort($expected);
        }

        static::assertEquals($expected, $characteristics);
    }

    public function variantCharacteristicsCases(): array
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

        $subLanguageId = $this->getSubLanguageId('en_sub');

        return [
            [
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
                null,
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria()),
                false,
            ],
            [
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
                ['red', 'xl', 'slim fit'],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                true,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $this->getDeDeLanguageId() => ['name' => 'rot'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $this->getDeDeLanguageId() => ['name' => 'extra gross'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $this->getDeDeLanguageId() => ['name' => 'schmal'],
                            ],
                        ],
                    ],
                ]),
                ['rot', 'extra gross', 'schmal'],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                true,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                ]),
                ['red', 'xl', 'slim fit'],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                true,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                            ],
                        ],
                    ],
                ]),
                ['red', 'xl', 'slim fit'],
                [$subLanguageId, $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                true,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $subLanguageId => ['name' => 'der'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $subLanguageId => ['name' => 'lx'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $subLanguageId => ['name' => 'tif mils'],
                            ],
                        ],
                    ],
                ]),
                ['der', 'lx', 'tif mils'],
                [$subLanguageId, $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                true,
            ],
            [
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
                ['red', 'xl', 'slim fit'],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                false,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $this->getDeDeLanguageId() => ['name' => 'rot'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $this->getDeDeLanguageId() => ['name' => 'extra gross'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
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
                ['rot', 'extra gross', 'schmal'],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                false,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
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
                ['red', 'xl', 'slim fit'],
                [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                false,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
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
                ['red', 'xl', 'slim fit'],
                [$subLanguageId, $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                false,
            ],
            [
                array_merge($defaults, [
                    'options' => [
                        [
                            'id' => $ids->get('red'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'red'],
                                $subLanguageId => ['name' => 'der'],
                            ],
                        ],
                        [
                            'id' => $ids->get('xl'),
                            'group' => ['id' => $ids->get('size'), 'name' => 'size'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'xl'],
                                $subLanguageId => ['name' => 'lx'],
                            ],
                        ],
                        [
                            'id' => $ids->get('slim-fit'),
                            'group' => ['id' => $ids->get('fit'), 'name' => 'fit'],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => ['name' => 'slim fit'],
                                $subLanguageId => ['name' => 'tif mils'],
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
                ['der', 'lx', 'tif mils'],
                [$subLanguageId, $this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                false,
            ],
            [
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
                ['slim fit', 'red', 'xl'],
                [Defaults::LANGUAGE_SYSTEM],
                (new Criteria())->addAssociation('options'),
                false,
            ],
        ];
    }

    private function getSubLanguageId(string $name): string
    {
        $subLanguageId = Uuid::randomHex();

        $subLanguage = [
            'id' => $subLanguageId,
            'name' => $name,
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
        ];

        $this->getContainer()
            ->get('language.repository')
            ->create([$subLanguage], Context::createDefaultContext());

        return $subLanguageId;
    }
}
