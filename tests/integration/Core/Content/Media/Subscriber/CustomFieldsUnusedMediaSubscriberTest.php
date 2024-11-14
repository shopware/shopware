<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use Shopware\Core\Content\Test\Category\CategoryBuilder;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(CustomFieldsUnusedMediaSubscriber::class)]
class CustomFieldsUnusedMediaSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $mediaRepository;

    private EntityRepository $customFieldSetRepository;

    protected function setUp(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        try {
            $connection->fetchOne('SELECT JSON_OVERLAPS(JSON_ARRAY(1), JSON_ARRAY(1));');
        } catch (\Exception $e) {
            static::markTestSkipped('JSON_OVERLAPS() function not supported on this database');
        }

        parent::setUp();

        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->customFieldSetRepository = $this->getContainer()->get('custom_field_set.repository');
    }

    public function testMediaIdsAreNotRemovedWhenMediaIsNotReferenced(): void
    {
        $mediaIds = array_values($this->createMedia(10)->all());

        $event = new UnusedMediaSearchEvent($mediaIds);
        $listener = new CustomFieldsUnusedMediaSubscriber(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );

        $listener->removeUsedMedia($event);

        static::assertSame($mediaIds, $event->getUnusedIds());
    }

    public function testMediaIdsFromAllPossibleLocationsAreRemovedFromEvent(): void
    {
        $mediaIds = $this->createContent();
        $event = new UnusedMediaSearchEvent($mediaIds);
        $listener = new CustomFieldsUnusedMediaSubscriber(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );
        $listener->removeUsedMedia($event);

        static::assertEmpty($event->getUnusedIds());
    }

    public function testUnusedMediaIsPresent(): void
    {
        $mediaIds = $this->createContent();

        $unusedMediaIds = array_values($this->createMedia(5, 10)->all());

        $event = new UnusedMediaSearchEvent([...$mediaIds, ...$unusedMediaIds]);
        $listener = new CustomFieldsUnusedMediaSubscriber(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );
        $listener->removeUsedMedia($event);

        static::assertEquals($unusedMediaIds, $event->getUnusedIds());
    }

    private function createMedia(int $num, ?int $start = null): IdsCollection
    {
        $ids = new IdsCollection();
        $media = [];
        foreach (range($start ?? 1, $num) as $i) {
            $media[] = [
                'id' => $ids->create('media-' . $i),
                'fileName' => "Media $i",
                'fileExtension' => 'jpg',
                'mimeType' => 'image/jpeg',
                'fileSize' => 12345,
            ];
        }
        $this->mediaRepository->create($media, Context::createDefaultContext());

        return $ids;
    }

    /**
     * @return array<string>
     */
    private function createContent(): array
    {
        $ids = $this->createMedia(9);

        $mediaIds = $ids->all();

        foreach (['product', 'category', 'order'] as $entity) {
            $this->createMediaCustomField($entity);
            $this->createMediaSelectCustomField($entity);
            $this->createMediaMultiSelectCustomField($entity);
        }

        $product = new ProductBuilder($ids, Uuid::randomHex(), 100);
        $product->price(100);
        $product->customField('custom_field_media_product', $ids->get('media-1'));
        $product->customField('custom_field_media_select_product', $ids->get('media-2'));
        $product->customField('custom_field_media_multi_select_product', [$ids->get('media-2'), $ids->get('media-3'), $ids->get('media-4')]);
        $products = [$product->build()];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $category = new CategoryBuilder($ids, 'Category');
        $category->customField('custom_field_media_category', $ids->get('media-5'));
        $category->customField('custom_field_media_select_category', $ids->get('media-6'));
        $category->customField('custom_field_media_multi_select_category', [$ids->get('media-7'), $ids->get('media-8'), $ids->get('media-9')]);

        $categories = [$category->build()];

        $this->getContainer()->get('category.repository')->create($categories, Context::createDefaultContext());

        return array_values($mediaIds);
    }

    /**
     * @param array<array{name: string, type: string, config: array<string, mixed>}> $fields
     */
    private function createFieldSet(string $entity, string $name, array $fields): void
    {
        $this->customFieldSetRepository->create(
            [
                [
                    'name' => $name,
                    'config' => [
                        'label' => [
                            'en-GB' => $name,
                        ],
                    ],
                    'customFields' => $fields,
                    'relations' => [
                        [
                            'entityName' => $entity,
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }

    private function createMediaCustomField(string $entity): void
    {
        $this->createFieldSet(
            $entity,
            'media_fieldset_' . $entity,
            [
                [
                    'name' => 'custom_field_media_' . $entity,
                    'type' => CustomFieldTypes::MEDIA,
                    'config' => [
                        'label' => [
                            'en-GB' => 'custom_field_media_' . $entity,
                        ],
                        'componentName' => 'sw-media-field',
                        'customFieldType' => 'media',
                        'customFieldPosition' => 1,
                    ],
                ],
            ]
        );
    }

    private function createMediaSelectCustomField(string $entity): void
    {
        $this->createFieldSet(
            $entity,
            'media_select_fieldset_' . $entity,
            [
                [
                    'name' => 'custom_field_media_select_' . $entity,
                    'type' => CustomFieldTypes::SELECT,
                    'config' => [
                        'entity' => 'media',
                        'componentName' => 'sw-entity-single-select',
                        'label' => [
                            'en-GB' => 'custom_field_media_select_' . $entity,
                        ],
                        'customFieldType' => 'select',
                        'customFieldPosition' => 1,
                    ],
                ],
            ]
        );
    }

    private function createMediaMultiSelectCustomField(string $entity): void
    {
        $this->createFieldSet(
            $entity,
            'media_multi_select_fieldset_' . $entity,
            [
                [
                    'name' => 'custom_field_media_multi_select_' . $entity,
                    'type' => CustomFieldTypes::SELECT,
                    'config' => [
                        'entity' => 'media',
                        'componentName' => 'sw-entity-multi-id-select',
                        'label' => [
                            'en-GB' => 'custom_field_media_select_' . $entity,
                        ],
                        'customFieldType' => 'select',
                        'customFieldPosition' => 1,
                    ],
                ],
            ]
        );
    }
}
