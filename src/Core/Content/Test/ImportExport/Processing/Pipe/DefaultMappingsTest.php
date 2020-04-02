<?php


namespace Shopware\Core\Content\Test\ImportExport\Processing\Pipe;


use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class DefaultMappingsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDefaultMediaMapping(): void
    {
        $mediaTranslations = [
            'alt' => 'alternate text',
            'title' => 'media title'
        ];

        $media = [
            'id' => Uuid::randomHex(),
            'mediaFolderId' => Uuid::randomHex(),
            'url' => 'https://shopware.test/foo/bar/media.png',
            'private' => false,
            'mediaType' => 'image/png',
            'translations' => [
                'DEFAULT' => $mediaTranslations
            ]
        ];

        $mapping = $this->getDefaultMapping('media');

        $config = new Config($mapping, []);
        $mappingPipe = new KeyMappingPipe($mapping, true);
        $mappedMedia = iterator_to_array($mappingPipe->in($config, $media));

        static::assertSame($media['id'], $mappedMedia['id']);
        static::assertSame($media['mediaFolderId'], $mappedMedia['folder_id']);
        static::assertSame($media['url'], $mappedMedia['url']);
        static::assertSame($media['private'], $mappedMedia['private']);
        static::assertSame($media['mediaType'], $mappedMedia['type']);

        static::assertSame($mediaTranslations['alt'], $mappedMedia['alt']);
        static::assertSame($mediaTranslations['title'], $mappedMedia['title']);

        $unmappedMedia = iterator_to_array($mappingPipe->out($config, $mappedMedia));
        $unmappedMediaTranslations = $unmappedMedia['translations']['DEFAULT'];

        static::assertSame($media['id'], $unmappedMedia['id']);
        static::assertSame($media['mediaFolderId'], $unmappedMedia['mediaFolderId']);
        static::assertSame($media['url'], $unmappedMedia['url']);
        static::assertSame($media['private'], $unmappedMedia['private']);
        static::assertSame($media['mediaType'], $unmappedMedia['mediaType']);

        static::assertSame($mediaTranslations['alt'], $unmappedMediaTranslations['alt']);
        static::assertSame($mediaTranslations['title'], $unmappedMediaTranslations['title']);
    }

    public function testProductMapping(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'parentVersionId' => null,
            'productNumber' => Uuid::randomHex(),
            'active' => false,
            'stock' => 10,

            'translations' => [
                'DEFAULT' => [
                    'name' => 'German',
                    'description' => 'Beschreibung',
                ],
            ],

            'price' => [
                'DEFAULT' => [
                    'gross' => 15,
                    'net' => 10,
                ],
            ],
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => '19%',
                'taxRate' => 19.0
            ],
            'cover' => [
                'media' => [
                    'id' => Uuid::randomHex(),
                    'mediaFolderId' => Uuid::randomHex(),
                    'url' => 'https://shopware.test/foo/bar/media.png',
                    'private' => false,
                    'mediaType' => 'image/png',
                    'translations' => [
                        'DEFAULT' => [
                            'title' => 'cover media title',
                            'alt' => 'cover media alt',
                        ]
                    ]
                ]
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'Some brand'
                    ]
                ]
            ],
            'categories' => Uuid::randomHex() . '|' . Uuid::randomHex(),
            'visibilities' => [
                'all' => Uuid::randomHex() . '|' . Uuid::randomHex(),
            ]
        ];

        $mapping = $this->getDefaultMapping('product');

        $config = new Config($mapping, []);
        $mappingPipe = new KeyMappingPipe($mapping, true);
        $mappedProduct = iterator_to_array($mappingPipe->in($config, $product));

        static::assertSame($product['id'], $mappedProduct['id']);

        static::assertSame($product['productNumber'], $mappedProduct['product_number']);
        static::assertSame($product['active'], $mappedProduct['active']);
        static::assertSame($product['stock'], $mappedProduct['stock']);
        static::assertSame($product['translations']['DEFAULT']['name'], $mappedProduct['name']);
        static::assertSame($product['translations']['DEFAULT']['description'], $mappedProduct['description']);

        static::assertSame($product['price']['DEFAULT']['net'], $mappedProduct['price_net']);
        static::assertSame($product['price']['DEFAULT']['gross'], $mappedProduct['price_gross']);

        static::assertSame($product['tax']['id'], $mappedProduct['tax_id']);
        static::assertSame($product['tax']['taxRate'], $mappedProduct['tax_rate']);
        static::assertSame($product['tax']['name'], $mappedProduct['tax_name']);

        static::assertSame($product['cover']['media']['id'], $mappedProduct['cover_media_id']);
        static::assertSame($product['cover']['media']['url'], $mappedProduct['cover_media_url']);
        static::assertSame($product['cover']['media']['translations']['DEFAULT']['title'], $mappedProduct['cover_media_title']);
        static::assertSame($product['cover']['media']['translations']['DEFAULT']['alt'], $mappedProduct['cover_media_alt']);

        static::assertSame($product['manufacturer']['id'], $mappedProduct['manufacturer_id']);
        static::assertSame($product['manufacturer']['translations']['DEFAULT']['name'], $mappedProduct['manufacturer_name']);

        static::assertSame($product['categories'], $mappedProduct['categories']);
        static::assertSame($product['visibilities']['all'], $mappedProduct['sales_channel']);


        $unmappedProduct = iterator_to_array($mappingPipe->out($config, $mappedProduct));

        static::assertSame($product['id'], $unmappedProduct['id']);

        static::assertSame($product['productNumber'], $unmappedProduct['productNumber']);
        static::assertSame($product['active'], $unmappedProduct['active']);
        static::assertSame($product['stock'], $unmappedProduct['stock']);
        static::assertSame($product['translations']['DEFAULT']['name'], $unmappedProduct['translations']['DEFAULT']['name']);
        static::assertSame($product['translations']['DEFAULT']['description'], $unmappedProduct['translations']['DEFAULT']['description']);

        static::assertSame($product['price']['DEFAULT']['net'], $unmappedProduct['price']['DEFAULT']['net']);
        static::assertSame($product['price']['DEFAULT']['gross'], $unmappedProduct['price']['DEFAULT']['gross']);

        static::assertSame($product['tax']['id'], $unmappedProduct['tax']['id']);
        static::assertSame($product['tax']['taxRate'], $unmappedProduct['tax']['taxRate']);
        static::assertSame($product['tax']['name'], $unmappedProduct['tax']['name']);

        static::assertSame($product['cover']['media']['id'], $unmappedProduct['cover']['media']['id']);
        static::assertSame($product['cover']['media']['url'], $unmappedProduct['cover']['media']['url']);
        static::assertSame($product['cover']['media']['translations']['DEFAULT']['title'], $unmappedProduct['cover']['media']['translations']['DEFAULT']['title']);
        static::assertSame($product['cover']['media']['translations']['DEFAULT']['alt'], $unmappedProduct['cover']['media']['translations']['DEFAULT']['alt']);

        static::assertSame($product['manufacturer']['id'], $unmappedProduct['manufacturer']['id']);
        static::assertSame($product['manufacturer']['translations']['DEFAULT']['name'], $unmappedProduct['manufacturer']['translations']['DEFAULT']['name']);

        static::assertSame($product['categories'], $unmappedProduct['categories']);
        static::assertSame($product['visibilities']['all'], $unmappedProduct['visibilities']['all']);
    }

    public function testCategoryMapping(): void
    {
        $category = [
            'id' => Uuid::randomHex(),
            'parentId' => Uuid::randomHex(),
            'active' => true,
            'type' => '',
            'visible' => true,
            'translations' => [
                'DEFAULT' => [
                    'name' => 'test',
                    'externalLink' => 'test',
                    'description' => 'test',
                    'metaTitle' => 'test',
                    'metaDescription' => 'test',
                ]
            ],
            'media' => [
                'id' => Uuid::randomHex(),
                'mediaFolderId' => Uuid::randomHex(),
                'url' => 'https://shopware.test/foo/bar/media.png',
                'private' => false,
                'mediaType' => 'image/png',
                'translations' => [
                    'DEFAULT' => [
                        'title' => 'media title',
                        'alt' => 'media alt'
                    ]
                ]
            ],
            'cmsPageId' => Uuid::randomHex()
        ];

        $mapping = $this->getDefaultMapping('category');

        $config = new Config($mapping, []);
        $mappingPipe = new KeyMappingPipe($mapping, true);
        $mappedCategory = iterator_to_array($mappingPipe->in($config, $category));
        

        static::assertSame($category['id'], $mappedCategory['id']);
        static::assertSame($category['parentId'], $mappedCategory['parent_id']);
        static::assertSame($category['active'], $mappedCategory['active']);

        static::assertSame($category['type'], $mappedCategory['type']);
        static::assertSame($category['visible'], $mappedCategory['visible']);
        static::assertSame($category['translations']['DEFAULT']['name'], $mappedCategory['name']);
        static::assertSame($category['translations']['DEFAULT']['externalLink'], $mappedCategory['external_link']);
        static::assertSame($category['translations']['DEFAULT']['description'], $mappedCategory['description']);
        static::assertSame($category['translations']['DEFAULT']['metaTitle'], $mappedCategory['meta_title']);
        static::assertSame($category['translations']['DEFAULT']['metaDescription'], $mappedCategory['meta_description']);

        static::assertSame($category['media']['id'], $mappedCategory['media_id']);
        static::assertSame($category['media']['url'], $mappedCategory['media_url']);
        static::assertSame($category['media']['mediaFolderId'], $mappedCategory['media_folder_id']);
        static::assertSame($category['media']['mediaType'], $mappedCategory['media_type']);
        static::assertSame($category['media']['translations']['DEFAULT']['title'], $mappedCategory['media_title']);
        static::assertSame($category['media']['translations']['DEFAULT']['alt'], $mappedCategory['media_alt']);

        static::assertSame($category['cmsPageId'], $mappedCategory['cms_page_id']);


        $unmappedCategory = iterator_to_array($mappingPipe->out($config, $mappedCategory));

        static::assertSame($category['id'], $unmappedCategory['id']);
        static::assertSame($category['parentId'], $unmappedCategory['parentId']);
        static::assertSame($category['active'], $unmappedCategory['active']);

        static::assertSame($category['type'], $unmappedCategory['type']);
        static::assertSame($category['visible'], $unmappedCategory['visible']);
        static::assertSame($category['translations']['DEFAULT']['name'], $unmappedCategory['translations']['DEFAULT']['name']);
        static::assertSame($category['translations']['DEFAULT']['externalLink'], $unmappedCategory['translations']['DEFAULT']['externalLink']);
        static::assertSame($category['translations']['DEFAULT']['description'], $unmappedCategory['translations']['DEFAULT']['description']);
        static::assertSame($category['translations']['DEFAULT']['metaTitle'], $unmappedCategory['translations']['DEFAULT']['metaTitle']);
        static::assertSame($category['translations']['DEFAULT']['metaDescription'], $unmappedCategory['translations']['DEFAULT']['metaDescription']);

        static::assertSame($category['media']['id'], $unmappedCategory['media']['id']);
        static::assertSame($category['media']['url'], $unmappedCategory['media']['url']);
        static::assertSame($category['media']['mediaFolderId'], $unmappedCategory['media']['mediaFolderId']);
        static::assertSame($category['media']['mediaType'], $unmappedCategory['media']['mediaType']);
        static::assertSame($category['media']['translations']['DEFAULT']['title'], $unmappedCategory['media']['translations']['DEFAULT']['title']);
        static::assertSame($category['media']['translations']['DEFAULT']['alt'], $unmappedCategory['media']['translations']['DEFAULT']['alt']);

        static::assertSame($category['cmsPageId'], $unmappedCategory['cmsPageId']);
    }

    private function getDefaultMapping(string $entity): MappingCollection
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sourceEntity', $entity));
        $criteria->addFilter(new EqualsFilter('systemDefault', true));

        /** @var ImportExportProfileEntity $profile */
        $profile = $profileRepository->search($criteria, Context::createDefaultContext())->first();
        return MappingCollection::fromIterable($profile->getMapping());
    }
}