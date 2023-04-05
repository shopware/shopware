<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1620374229UpdateCustomFieldNameInProductStreamTable;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('core')]
class Migration1620374229UpdateCustomFieldNameInProductStreamTableTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $productStreamRepository;

    protected function setUp(): void
    {
        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');

        $customFieldRepository->create([
            [
                'name' => 'swag_example_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'relations' => [[
                    'entityName' => 'product',
                ]],
                'customFields' => [
                    [
                        'name' => 'custom_field_a',
                        'type' => CustomFieldTypes::INT,
                    ],
                    [
                        'name' => 'custom_field_b',
                        'type' => CustomFieldTypes::TEXT,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    public function testUpdateCustomFieldWithPrefix(): void
    {
        $ids = new IdsCollection();
        $customField = 'customFields.custom_field_a';

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test',
            'filters' => [
                [
                    'id' => $ids->get('filters'),
                    'type' => 'equals',
                    'field' => $customField,
                    'value' => '1',
                ],
            ],
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

        $migration = new Migration1620374229UpdateCustomFieldNameInProductStreamTable();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$ids->get('stream')]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $stream */
        $stream = $this->productStreamRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals([[
            'type' => 'equals',
            'field' => 'product.' . $customField,
            'value' => '1',
        ]], $stream->getApiFilter());
        static::assertInstanceOf(ProductStreamFilterCollection::class, $stream->getFilters());
        static::assertInstanceOf(ProductStreamFilterEntity::class, $stream->getFilters()->first());
        static::assertEquals($customField, $stream->getFilters()->first()->getField());
    }

    public function testUpdateCustomFieldWithoutPrefix(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test',
            'filters' => [
                [
                    'id' => $ids->get('filters'),
                    'type' => 'equals',
                    'field' => 'custom_field_a',
                    'value' => '1',
                ],
            ],
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

        $migration = new Migration1620374229UpdateCustomFieldNameInProductStreamTable();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$ids->get('stream')]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $stream */
        $stream = $this->productStreamRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals([[
            'type' => 'equals',
            'field' => 'product.customFields.custom_field_a',
            'value' => '1',
        ]], $stream->getApiFilter());
        static::assertInstanceOf(ProductStreamFilterCollection::class, $stream->getFilters());
        static::assertInstanceOf(ProductStreamFilterEntity::class, $stream->getFilters()->first());
        static::assertEquals('customFields.custom_field_a', $stream->getFilters()->first()->getField());
    }

    public function testUpdateCustomFieldWithUnknownField(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test',
            'filters' => [
                [
                    'id' => $ids->get('filters'),
                    'type' => 'equals',
                    'field' => 'active',
                    'value' => '1',
                ],
            ],
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

        $migration = new Migration1620374229UpdateCustomFieldNameInProductStreamTable();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$ids->get('stream')]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $stream */
        $stream = $this->productStreamRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals([[
            'type' => 'equals',
            'field' => 'product.active',
            'value' => '1',
        ]], $stream->getApiFilter());
        static::assertInstanceOf(ProductStreamFilterCollection::class, $stream->getFilters());
        static::assertInstanceOf(ProductStreamFilterEntity::class, $stream->getFilters()->first());
        static::assertEquals('active', $stream->getFilters()->first()->getField());
    }
}
