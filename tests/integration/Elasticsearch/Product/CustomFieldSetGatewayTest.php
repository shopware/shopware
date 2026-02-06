<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Product\CustomFieldSetGateway;

/**
 * @internal
 */
#[CoversClass(CustomFieldSetGateway::class)]
class CustomFieldSetGatewayTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('custom-field-set-1'),
                'name' => 'swag_example_set1',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'product'],
                    ['entityName' => 'customer'],
                ],
                'customFields' => [
                    [
                        'id' => $this->ids->get('custom-field-1'),
                        'name' => 'test_newly_created_field',
                        'type' => CustomFieldTypes::INT,
                        'includeInSearch' => true,
                    ],
                    [
                        'id' => $this->ids->get('custom-field-2'),
                        'name' => 'test_newly_created_field_text',
                        'type' => CustomFieldTypes::TEXT,
                        'includeInSearch' => true,
                    ],
                    [
                        'id' => $this->ids->get('custom-field-4'),
                        'name' => 'test_non_searchable_field',
                        'type' => CustomFieldTypes::TEXT,
                        'includeInSearch' => false,
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('custom-field-set-2'),
                'name' => 'swag_example_set2',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'id' => $this->ids->get('custom-field-3'),
                        'name' => 'test_newly_created_field3',
                        'type' => CustomFieldTypes::INT,
                        'includeInSearch' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    protected function tearDown(): void
    {
        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->delete([
            ['id' => $this->ids->get('custom-field-set-1')],
            ['id' => $this->ids->get('custom-field-set-2')],
        ], Context::createDefaultContext());
    }

    public function testFetchCustomFieldsForSets(): void
    {
        $result = static::getContainer()
            ->get(CustomFieldSetGateway::class)
            ->fetchCustomFieldsForSets([
                $this->ids->get('custom-field-set-1'),
            ]);

        static::assertSame([
            $this->ids->get('custom-field-set-1') => [
                [
                    'id' => $this->ids->get('custom-field-1'),
                    'name' => 'test_newly_created_field',
                    'type' => 'int',
                ],
                [
                    'id' => $this->ids->get('custom-field-2'),
                    'name' => 'test_newly_created_field_text',
                    'type' => 'text',
                ],
            ],
        ], $result);
    }

    public function testFetchIndexableCustomFieldsForSetsWithIncludeInSearch(): void
    {
        $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

        $result = $gateway->fetchIndexableCustomFieldsForSets(
            [$this->ids->get('custom-field-set-1')],
            [],
            []
        );

        // Should only return fields with includeInSearch = true
        static::assertCount(1, $result);
        static::assertArrayHasKey($this->ids->get('custom-field-set-1'), $result);
        static::assertCount(2, $result[$this->ids->get('custom-field-set-1')]);
    }

    public function testFetchIndexableCustomFieldsForSetsWithUsedFieldNames(): void
    {
        $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

        $result = $gateway->fetchIndexableCustomFieldsForSets(
            [$this->ids->get('custom-field-set-1')],
            ['test_non_searchable_field'], // This field has includeInSearch = false
            []
        );

        // Should return fields with includeInSearch = true OR fields in usedFieldNames
        static::assertCount(1, $result);
        static::assertArrayHasKey($this->ids->get('custom-field-set-1'), $result);
        static::assertCount(3, $result[$this->ids->get('custom-field-set-1')]);

        $fieldNames = array_column($result[$this->ids->get('custom-field-set-1')], 'name');
        static::assertContains('test_non_searchable_field', $fieldNames);
    }

    public function testFetchIndexableCustomFieldsForSetsWithAppOwnedSets(): void
    {
        // Create an app-owned custom field set
        $connection = static::getContainer()->get(Connection::class);
        $appId = Uuid::randomHex();

        // Insert a mock app
        $connection->insert('app', [
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'TestApp',
            'path' => 'test',
            'version' => '1.0.0',
            'active' => 1,
            'configurable' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('app-field-set'),
                'name' => 'app_example_set',
                'appId' => $appId,
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'id' => $this->ids->get('app-field-1'),
                        'name' => 'app_custom_field',
                        'type' => CustomFieldTypes::TEXT,
                        'includeInSearch' => false,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            $result = $gateway->fetchIndexableCustomFieldsForSets(
                [$this->ids->get('app-field-set')],
                [],
                [$this->ids->get('app-field-set')] // App-owned set
            );

            // Should return fields from app-owned sets even if includeInSearch = false
            static::assertCount(1, $result);
            static::assertArrayHasKey($this->ids->get('app-field-set'), $result);
            static::assertSame('app_custom_field', $result[$this->ids->get('app-field-set')][0]['name']);
        } finally {
            $customFieldRepository->delete([
                ['id' => $this->ids->get('app-field-set')],
            ], Context::createDefaultContext());

            $connection->delete('app', ['id' => Uuid::fromHexToBytes($appId)]);
        }
    }

    public function testFetchAppOwnedFieldSetIds(): void
    {
        // Create an app-owned custom field set
        $connection = static::getContainer()->get(Connection::class);
        $appId = Uuid::randomHex();

        // Insert a mock app
        $connection->insert('app', [
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'TestApp',
            'path' => 'test',
            'version' => '1.0.0',
            'active' => 1,
            'configurable' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('app-field-set'),
                'name' => 'app_example_set',
                'appId' => $appId,
                'relations' => [
                    ['entityName' => 'product'],
                ],
            ],
        ], Context::createDefaultContext());

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            $result = $gateway->fetchAppOwnedFieldSetIds([
                $this->ids->get('custom-field-set-1'),
                $this->ids->get('app-field-set'),
            ]);

            static::assertCount(1, $result);
            static::assertContains($this->ids->get('app-field-set'), $result);
            static::assertNotContains($this->ids->get('custom-field-set-1'), $result);
        } finally {
            $customFieldRepository->delete([
                ['id' => $this->ids->get('app-field-set')],
            ], Context::createDefaultContext());

            $connection->delete('app', ['id' => Uuid::fromHexToBytes($appId)]);
        }
    }

    public function testIsAppOwnedFieldSet(): void
    {
        // Create an app-owned custom field set
        $connection = static::getContainer()->get(Connection::class);
        $appId = Uuid::randomHex();

        // Insert a mock app
        $connection->insert('app', [
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'TestApp',
            'path' => 'test',
            'version' => '1.0.0',
            'active' => 1,
            'configurable' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('app-field-set'),
                'name' => 'app_example_set',
                'appId' => $appId,
                'relations' => [
                    ['entityName' => 'product'],
                ],
            ],
        ], Context::createDefaultContext());

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            static::assertTrue($gateway->isAppOwnedFieldSet($this->ids->get('app-field-set')));
            static::assertFalse($gateway->isAppOwnedFieldSet($this->ids->get('custom-field-set-1')));
        } finally {
            $customFieldRepository->delete([
                ['id' => $this->ids->get('app-field-set')],
            ], Context::createDefaultContext());

            $connection->delete('app', ['id' => Uuid::fromHexToBytes($appId)]);
        }
    }

    public function testFetchCustomFieldNamesBySetIds(): void
    {
        $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

        $result = $gateway->fetchCustomFieldNamesBySetIds([
            $this->ids->get('custom-field-set-1'),
            $this->ids->get('custom-field-set-2'),
        ]);

        // Should return all active custom field names from the sets
        static::assertCount(4, $result);
        static::assertContains('test_newly_created_field', $result);
        static::assertContains('test_newly_created_field_text', $result);
        static::assertContains('test_non_searchable_field', $result);
        static::assertContains('test_newly_created_field3', $result);
    }

    public function testFetchCustomFieldNamesBySetIdsWithEmptyArray(): void
    {
        $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

        $result = $gateway->fetchCustomFieldNamesBySetIds([]);

        static::assertEmpty($result);
    }

    public function testFetchCustomFieldNamesUsedInProductSorting(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $sortingId = Uuid::randomHex();

        $connection->insert('product_sorting', [
            'id' => Uuid::fromHexToBytes($sortingId),
            'url_key' => 'test-sorting',
            'priority' => 1,
            'active' => 1,
            'locked' => 0,
            'fields' => json_encode([
                ['field' => 'customFields.test_sorting_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 0],
                ['field' => 'product.name', 'order' => 'asc', 'priority' => 0, 'naturalSorting' => 0],
            ]),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            $result = $gateway->fetchCustomFieldNamesUsedInProductSorting();

            static::assertContains('test_sorting_field', $result);
        } finally {
            $connection->delete('product_sorting', ['id' => Uuid::fromHexToBytes($sortingId)]);
        }
    }

    public function testFetchCustomFieldNamesUsedInProductSortingWithCandidateFilter(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $sortingId = Uuid::randomHex();

        $connection->insert('product_sorting', [
            'id' => Uuid::fromHexToBytes($sortingId),
            'url_key' => 'test-sorting-filter',
            'priority' => 1,
            'active' => 1,
            'locked' => 0,
            'fields' => json_encode([
                ['field' => 'customFields.candidate_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 0],
                ['field' => 'customFields.other_field', 'order' => 'asc', 'priority' => 0, 'naturalSorting' => 0],
            ]),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            // Only search for 'candidate_field' in the candidates
            $result = $gateway->fetchCustomFieldNamesUsedInProductSorting(['candidate_field']);

            static::assertContains('candidate_field', $result);
            static::assertNotContains('other_field', $result);
        } finally {
            $connection->delete('product_sorting', ['id' => Uuid::fromHexToBytes($sortingId)]);
        }
    }

    public function testFetchCustomFieldNamesUsedInProductStream(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $streamId = Uuid::randomHex();
        $filterId = Uuid::randomHex();

        $connection->insert('product_stream', [
            'id' => Uuid::fromHexToBytes($streamId),
            'invalid' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $connection->insert('product_stream_filter', [
            'id' => Uuid::fromHexToBytes($filterId),
            'product_stream_id' => Uuid::fromHexToBytes($streamId),
            'type' => 'equals',
            'field' => 'customFields.test_stream_field',
            'value' => '"test"',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            $result = $gateway->fetchCustomFieldNamesUsedInProductStream();

            static::assertContains('test_stream_field', $result);
        } finally {
            $connection->delete('product_stream_filter', ['id' => Uuid::fromHexToBytes($filterId)]);
            $connection->delete('product_stream', ['id' => Uuid::fromHexToBytes($streamId)]);
        }
    }

    public function testFetchCustomFieldNamesUsedInProductStreamWithCandidateFilter(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $streamId = Uuid::randomHex();
        $filterId1 = Uuid::randomHex();
        $filterId2 = Uuid::randomHex();

        $connection->insert('product_stream', [
            'id' => Uuid::fromHexToBytes($streamId),
            'invalid' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $connection->insert('product_stream_filter', [
            'id' => Uuid::fromHexToBytes($filterId1),
            'product_stream_id' => Uuid::fromHexToBytes($streamId),
            'type' => 'equals',
            'field' => 'customFields.candidate_stream_field',
            'value' => '"test"',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $connection->insert('product_stream_filter', [
            'id' => Uuid::fromHexToBytes($filterId2),
            'product_stream_id' => Uuid::fromHexToBytes($streamId),
            'type' => 'equals',
            'field' => 'customFields.other_stream_field',
            'value' => '"test"',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

            // Only search for 'candidate_stream_field' in the candidates
            $result = $gateway->fetchCustomFieldNamesUsedInProductStream(['candidate_stream_field']);

            static::assertContains('candidate_stream_field', $result);
            static::assertNotContains('other_stream_field', $result);
        } finally {
            $connection->delete('product_stream_filter', ['id' => Uuid::fromHexToBytes($filterId1)]);
            $connection->delete('product_stream_filter', ['id' => Uuid::fromHexToBytes($filterId2)]);
            $connection->delete('product_stream', ['id' => Uuid::fromHexToBytes($streamId)]);
        }
    }

    public function testFetchFieldSetIds(): void
    {
        $result = static::getContainer()
            ->get(CustomFieldSetGateway::class)
            ->fetchFieldSetIds([
                $this->ids->get('custom-field-1'),
                $this->ids->get('custom-field-2'),
                $this->ids->get('custom-field-3'),
            ]);

        static::assertSame([
            $this->ids->get('custom-field-1') => $this->ids->get('custom-field-set-1'),
            $this->ids->get('custom-field-2') => $this->ids->get('custom-field-set-1'),
            $this->ids->get('custom-field-3') => $this->ids->get('custom-field-set-2'),
        ], $result);
    }

    public function testFetchFieldSetEntityMappings(): void
    {
        $result = static::getContainer()
            ->get(CustomFieldSetGateway::class)
            ->fetchFieldSetEntityMappings([
                $this->ids->get('custom-field-set-1'),
                $this->ids->get('custom-field-set-2'),
            ]);

        static::assertSame([
            $this->ids->get('custom-field-set-1') => ['customer', 'product'],
            $this->ids->get('custom-field-set-2') => ['product'],
        ], $result);
    }
}
