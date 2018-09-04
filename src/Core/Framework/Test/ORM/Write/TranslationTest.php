<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Language\LanguageDefinition;

class TranslationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

    protected function setUp()
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testProductWithDifferentTranslations(): void
    {
        $data = [
            [
                'id' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                'manufacturer' => [
                    'id' => 'e4e8988334a34bb48d397b41a611084f',
                    'name' => 'Das blaue Haus',
                    'link' => 'http://www.blaueshaus-shop.de',
                ],
                'tax' => [
                    'id' => 'fe4eb0fd92a7417ebf8720a5148aae64',
                    'taxRate' => 19,
                    'name' => '19%',
                ],
                'price' => [
                    'gross' => 7.9899999999999824,
                    'net' => 6.7142857142857,
                ],
                'translations' => [
                    'f32f19ca62994c4bbd004296b35a5c24' => [
                        'id' => '4f1bcf3bc0fb4e62989e88b3bd37d1a2',
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'name' => 'Backform gelb',
                        'description' => 'inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.',
                        'language' => [
                                'id' => 'f32f19ca62994c4bbd004296b35a5c24',
                                'localeId' => '20080911ffff4fffafffffff19830531',
                                'name' => 'de_DE',
                            ],
                    ],
                    Defaults::LANGUAGE => [
                        'name' => 'Test En',
                    ],
                ],
                'cover' => [
                    'id' => 'd610dccf27754a7faa5c22d7368e6d8f',
                    'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                    'position' => 1,
                    'media' => [
                        'id' => '4b2252d11baa49f3a62e292888f5e439',
                        'name' => 'Backform-gelb',
                        'album' => [
                            'id' => 'a7104eb19fc649fa86cf6fe6c26ad65a',
                            'name' => 'Artikel',
                            'position' => 2,
                            'createThumbnails' => false,
                            'thumbnailSize' => '200x200;600x600;1280x1280',
                            'icon' => 'sprite-inbox',
                            'thumbnailHighDpi' => true,
                            'thumbnailQuality' => 90,
                            'thumbnailHighDpiQuality' => 60,
                        ],
                    ],
                ],
                'active' => true,
                'isCloseout' => false,
                'pseudoSales' => 0,
                'markAsTopseller' => false,
                'allowNotification' => false,
                'sales' => 0,
                'stock' => 45,
                'minStock' => 0,
                'position' => 0,
                'weight' => 0,
                'minPurchase' => 1,
                'shippingFree' => false,
                'purchasePrice' => 0,
            ],
        ];

        $result = $this->repository->create($data, $this->context);

        $products = $result->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $products->getIds());

        $languages = $result->getEventByDefinition(LanguageDefinition::class);
        static::assertCount(1, array_unique($languages->getIds()));
        static::assertContains('f32f19ca62994c4bbd004296b35a5c24', $languages->getIds());

        $translations = $result->getEventByDefinition(MediaTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $translations = array_column($translations->getPayload(), 'languageId');
        static::assertContains('f32f19ca62994c4bbd004296b35a5c24', $translations);

        $translations = $result->getEventByDefinition(ProductManufacturerTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $translations = array_column($translations->getPayload(), 'languageId');
        static::assertContains('f32f19ca62994c4bbd004296b35a5c24', $translations);

        $translations = $result->getEventByDefinition(ProductTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $translations = array_column($translations->getPayload(), 'languageId');
        static::assertContains(Defaults::LANGUAGE, $translations);
        static::assertContains('f32f19ca62994c4bbd004296b35a5c24', $translations);
    }
}
