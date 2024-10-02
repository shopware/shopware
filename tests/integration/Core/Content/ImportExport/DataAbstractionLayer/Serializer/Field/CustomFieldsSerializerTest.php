<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\CustomFieldsSerializer;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomFieldsSerializer::class)]
class CustomFieldsSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @var string
     */
    public const CUSTOM_FIELD_TEXT = 'test_custom_field_text';
    /**
     * @var string
     */
    public const CUSTOM_FIELD_NUMBER = 'test_custom_field_number';

    /**
     * @param array<int, Mapping> $mappings
     */
    #[DataProvider('serializeDataProvider')]
    public function testSerialize(array $mappings, Field $field, mixed $inputValue, mixed $expected): void
    {
        $this->setupCustomFields();

        $fieldSerializer = $this->getContainer()->get(CustomFieldsSerializer::class);
        static::assertInstanceOf(CustomFieldsSerializer::class, $fieldSerializer);

        $config = new Config(
            $mappings,
            [
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
                'profileName' => 'TestCustomFieldsSerializer',
                'sourceEntity' => 'product',
                'createEntities' => true,
                'updateEntities' => true,
            ],
            []
        );

        $resultIterable = $fieldSerializer->serialize($config, $field, $inputValue);

        static::assertSame($expected, iterator_to_array($resultIterable));
    }

    /**
     * @return iterable<string, array{field: Field, inputValue: mixed, expected: mixed}>
     */
    public static function serializeDataProvider(): iterable
    {
        yield 'expect nothing for non existing value' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKey'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_NUMBER => 42,
            ],
            'expected' => [
                'customFields' => '{"test_custom_field_number":42}',
                'customFields.test_custom_field_number' => 42,
            ],
        ];
        yield 'expect null for null value' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKey'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_TEXT => null,
                self::CUSTOM_FIELD_NUMBER => 42,
            ],
            'expected' => [
                'customFields' => '{"test_custom_field_number":42,"test_custom_field_text":null}',
                'customFields.test_custom_field_number' => 42,
                'customFields.test_custom_field_text' => null,
            ],
        ];
        yield 'expect nothing if the entity has no custom fields' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKeyText'
                ),
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_NUMBER,
                    'mappedKeyNumber'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [],
            'expected' => [
                'customFields' => '[]',
            ],
        ];
        yield 'expect values for both custom fields' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKeyText'
                ),
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_NUMBER,
                    'mappedKeyNumber'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_NUMBER => 96,
                self::CUSTOM_FIELD_TEXT => 'foobar',
            ],
            'expected' => [
                'customFields' => '{"test_custom_field_number":96,"test_custom_field_text":"foobar"}',
                'customFields.test_custom_field_number' => 96,
                'customFields.test_custom_field_text' => 'foobar',
            ],
        ];
    }

    /**
     * @param array<int, Mapping> $mappings
     */
    #[DataProvider('deserializeDataProvider')]
    public function testDeserialize(array $mappings, Field $field, mixed $inputValue, mixed $expected): void
    {
        $this->setupCustomFields();

        $fieldSerializer = $this->getContainer()->get(CustomFieldsSerializer::class);
        static::assertInstanceOf(CustomFieldsSerializer::class, $fieldSerializer);

        $config = new Config(
            $mappings,
            [
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
                'profileName' => 'TestCustomFieldsSerializer',
                'sourceEntity' => 'product',
                'createEntities' => true,
                'updateEntities' => true,
            ],
            []
        );

        $result = $fieldSerializer->deserialize($config, $field, $inputValue);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{field: Field, inputValue: mixed, expected: mixed}>
     */
    public static function deserializeDataProvider(): iterable
    {
        yield 'expect null for no value' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKey'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [],
            'expected' => null,
        ];
        yield 'expect null for empty value' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKey'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_TEXT => '',
            ],
            'expected' => null,
        ];
        yield 'expect null for two empty values' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKeyText'
                ),
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_NUMBER,
                    'mappedKeyNumber'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_TEXT => '',
                self::CUSTOM_FIELD_NUMBER => '',
            ],
            'expected' => null,
        ];
        yield 'expect one of two values if one is empty' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKeyText'
                ),
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_NUMBER,
                    'mappedKeyNumber'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_TEXT => '',
                self::CUSTOM_FIELD_NUMBER => 99,
            ],
            'expected' => [
                self::CUSTOM_FIELD_NUMBER => 99,
            ],
        ];
        yield 'expect two values if both are defined' => [
            'mappings' => [
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_TEXT,
                    'mappedKeyText'
                ),
                new Mapping(
                    'translations.DEFAULT.customFields.' . self::CUSTOM_FIELD_NUMBER,
                    'mappedKeyNumber'
                ),
            ],
            'field' => new CustomFields('custom_fields', 'customFields'),
            'inputValue' => [
                self::CUSTOM_FIELD_TEXT => 'hello world',
                self::CUSTOM_FIELD_NUMBER => 99,
            ],
            'expected' => [
                self::CUSTOM_FIELD_TEXT => 'hello world',
                self::CUSTOM_FIELD_NUMBER => 99,
            ],
        ];
    }

    private function setupCustomFields(): void
    {
        /** @var EntityRepository<CustomFieldSetCollection> $customFieldSetRepo */
        $customFieldSetRepo = $this->getContainer()->get('custom_field_set.repository');
        static::assertInstanceOf(EntityRepository::class, $customFieldSetRepo);

        $customFieldSetRepo->create([
            [
                'name' => 'test_set',
                'active' => true,
                'relations' => [
                    [
                        'entityName' => 'product',
                    ],
                ],
                'customFields' => [
                    [
                        'name' => self::CUSTOM_FIELD_TEXT,
                        'type' => 'text',
                        'active' => true,
                    ],
                    [
                        'name' => self::CUSTOM_FIELD_NUMBER,
                        'type' => 'number',
                        'active' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
