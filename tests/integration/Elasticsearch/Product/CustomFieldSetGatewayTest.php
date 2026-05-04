<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Product\CustomFieldSetGateway;

/**
 * @internal
 */
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

    public function testFetchCustomFieldsForSetsReturnsOnlyIncludeInSearch(): void
    {
        $gateway = static::getContainer()->get(CustomFieldSetGateway::class);

        $result = $gateway->fetchCustomFieldsForSets(
            [$this->ids->get('custom-field-set-1')]
        );

        // Should only return fields with includeInSearch = true
        static::assertCount(1, $result);
        static::assertArrayHasKey($this->ids->get('custom-field-set-1'), $result);
        static::assertCount(2, $result[$this->ids->get('custom-field-set-1')]);
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
