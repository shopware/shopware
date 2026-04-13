<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\AutoIncrement;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\CustomFields as CustomFieldsAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ListField as ListFieldAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Password;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Serialized;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AsArray;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited as InheritedFlag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited as ReverseInheritedFlag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking as SearchRankingFlag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SerializedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntity;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityCollection;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithInheritance;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithSearchRanking;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\StringEnum;

/**
 * @internal
 */
#[CoversClass(AttributeEntityCompiler::class)]
class AttributeEntityCompilerTest extends TestCase
{
    public function testCompile(): void
    {
        $compiledResult = (new AttributeEntityCompiler())->compile(AttributeEntity::class);

        static::assertSame($this->getExpectedCompilationResult(), $compiledResult);
    }

    public function testInheritedAttributeCompilesCorrectly(): void
    {
        $compiledResult = (new AttributeEntityCompiler())->compile(AttributeEntityWithInheritance::class);

        $entityDefinition = $this->findEntityDefinition($compiledResult, 'attribute_entity_inheritance');
        $fields = array_column($entityDefinition['fields'], null, 'name');

        $inheritedStringField = $fields['inheritedString'] ?? null;
        $inheritedCurrencyIdField = $fields['currencyId'] ?? null;
        $inheritedCurrencyField = $fields['currency'] ?? null;
        $inheritedWithForeignKeyField = $fields['inheritedWithForeignKey'] ?? null;
        $inheritedProductField = $fields['product'] ?? null;

        static::assertNotNull($inheritedStringField, 'inheritedString field not found');
        static::assertArrayHasKey(InheritedFlag::class, $inheritedStringField['flags'], 'inheritedString should have Inherited flag');
        static::assertIsArray($inheritedStringField['flags'][InheritedFlag::class]);
        static::assertSame(InheritedFlag::class, $inheritedStringField['flags'][InheritedFlag::class]['class']);
        static::assertSame(['foreignKey' => null], $inheritedStringField['flags'][InheritedFlag::class]['args'] ?? null);

        static::assertNotNull($inheritedCurrencyIdField, 'currencyId field not found');
        static::assertArrayHasKey(InheritedFlag::class, $inheritedCurrencyIdField['flags'], 'currencyId should have Inherited flag');
        static::assertIsArray($inheritedCurrencyIdField['flags'][InheritedFlag::class]);
        static::assertSame(InheritedFlag::class, $inheritedCurrencyIdField['flags'][InheritedFlag::class]['class']);

        static::assertNotNull($inheritedCurrencyField, 'currency field not found');
        static::assertArrayHasKey(InheritedFlag::class, $inheritedCurrencyField['flags'], 'currency should have Inherited flag');
        static::assertIsArray($inheritedCurrencyField['flags'][InheritedFlag::class]);
        static::assertSame(InheritedFlag::class, $inheritedCurrencyField['flags'][InheritedFlag::class]['class']);

        static::assertNotNull($inheritedWithForeignKeyField, 'inheritedWithForeignKey field not found');
        static::assertArrayHasKey(InheritedFlag::class, $inheritedWithForeignKeyField['flags'], 'inheritedWithForeignKey should have Inherited flag');
        static::assertIsArray($inheritedWithForeignKeyField['flags'][InheritedFlag::class]);
        static::assertSame(InheritedFlag::class, $inheritedWithForeignKeyField['flags'][InheritedFlag::class]['class']);
        static::assertSame(['foreignKey' => 'custom_fk'], $inheritedWithForeignKeyField['flags'][InheritedFlag::class]['args'] ?? null, 'foreignKey parameter should be passed through');

        static::assertNotNull($inheritedProductField, 'product field not found');
        static::assertArrayHasKey(ReverseInheritedFlag::class, $inheritedProductField['flags'], 'product should have ReverseInherited flag');
        static::assertIsArray($inheritedProductField['flags'][ReverseInheritedFlag::class]);
        static::assertSame(ReverseInheritedFlag::class, $inheritedProductField['flags'][ReverseInheritedFlag::class]['class']);
        static::assertSame(['propertyName' => 'attributed'], $inheritedProductField['flags'][ReverseInheritedFlag::class]['args'] ?? null);
    }

    public function testSearchRankingAttributeCompilesCorrectly(): void
    {
        $compiledResult = (new AttributeEntityCompiler())->compile(AttributeEntityWithSearchRanking::class);

        $entityDefinition = $this->findEntityDefinition($compiledResult, 'attribute_entity_search_ranking');
        $fields = array_column($entityDefinition['fields'], null, 'name');

        $currencyField = $fields['currency'] ?? null;
        $middleRankedStringField = $fields['middleRankedString'] ?? null;
        $lowRankedStringField = $fields['lowRankedString'] ?? null;
        $highRankedStringField = $fields['highRankedString'] ?? null;

        static::assertNotNull($currencyField, 'currency field not found');
        static::assertArrayHasKey(SearchRankingFlag::class, $currencyField['flags'], 'currency should have SearchRanking flag');
        static::assertIsArray($currencyField['flags'][SearchRankingFlag::class]);
        static::assertSame(SearchRankingFlag::class, $currencyField['flags'][SearchRankingFlag::class]['class']);
        static::assertSame(['ranking' => SearchRanking::ASSOCIATION_SEARCH_RANKING, 'tokenize' => true], $currencyField['flags'][SearchRankingFlag::class]['args'] ?? null);

        static::assertNotNull($middleRankedStringField, 'middle ranked string field not found');
        static::assertArrayHasKey(SearchRankingFlag::class, $middleRankedStringField['flags'], 'middle ranked string field should have SearchRanking flag');
        static::assertIsArray($middleRankedStringField['flags'][SearchRankingFlag::class]);
        static::assertSame(SearchRankingFlag::class, $middleRankedStringField['flags'][SearchRankingFlag::class]['class']);
        static::assertSame(['ranking' => SearchRanking::MIDDLE_SEARCH_RANKING, 'tokenize' => false], $middleRankedStringField['flags'][SearchRankingFlag::class]['args'] ?? null);

        static::assertNotNull($lowRankedStringField, 'low ranked string field not found');
        static::assertArrayHasKey(SearchRankingFlag::class, $lowRankedStringField['flags'], 'low ranked string field should have SearchRanking flag');
        static::assertIsArray($lowRankedStringField['flags'][SearchRankingFlag::class]);
        static::assertSame(SearchRankingFlag::class, $lowRankedStringField['flags'][SearchRankingFlag::class]['class']);
        static::assertSame(['ranking' => SearchRanking::LOW_SEARCH_RANKING, 'tokenize' => true], $lowRankedStringField['flags'][SearchRankingFlag::class]['args'] ?? null);

        static::assertNotNull($highRankedStringField, 'high ranked string field not found');
        static::assertArrayHasKey(SearchRankingFlag::class, $highRankedStringField['flags'], 'high ranked string field should have SearchRanking flag');
        static::assertIsArray($highRankedStringField['flags'][SearchRankingFlag::class]);
        static::assertSame(SearchRankingFlag::class, $highRankedStringField['flags'][SearchRankingFlag::class]['class']);
        static::assertSame(['ranking' => SearchRanking::HIGH_SEARCH_RANKING, 'tokenize' => false], $highRankedStringField['flags'][SearchRankingFlag::class]['args'] ?? null);
    }

    public function testCompileReturnsEmptyArrayForClassWithoutEntityAttribute(): void
    {
        $result = (new AttributeEntityCompiler())->compile(Entity::class);

        static::assertSame([], $result);
    }

    /**
     * @param list<array<string, mixed>> $compiledResult
     *
     * @return array<string, mixed>
     */
    private function findEntityDefinition(array $compiledResult, string $entityName): array
    {
        $filtered = array_filter(
            $compiledResult,
            static fn (array $result) => $result['type'] === 'entity' && $result['entity_name'] === $entityName
        );

        return array_values($filtered)[0] ?? static::fail('Entity definition "' . $entityName . '" not found');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getExpectedCompilationResult(): array
    {
        return [
            [
                'type' => 'mapping',
                'parent' => null,
                'entity_class' => ArrayEntity::class,
                'entity_name' => 'attribute_entity_currency',
                'fields' => [
                    [
                        'class' => FkField::class,
                        'translated' => false,
                        'args' => [
                            'attribute_entity_id',
                            'attributeEntityId',
                            'attribute_entity',
                        ],
                        'flags' => [
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                    ],
                    [
                        'class' => FkField::class,
                        'translated' => false,
                        'args' => [
                            'currency_id',
                            'currencyId',
                            'currency',
                        ],
                        'flags' => [
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                    ],
                    [
                        'class' => ManyToOneAssociationField::class,
                        'translated' => false,
                        'args' => [
                            'attributeEntity',
                            'attribute_entity_id',
                            'attribute_entity',
                            'id',
                        ],
                        'flags' => [],
                    ],
                    [
                        'class' => ManyToOneAssociationField::class,
                        'translated' => false,
                        'args' => [
                            'currency',
                            'currency_id',
                            'currency',
                            'id',
                        ],
                        'flags' => [],
                    ],
                ],
                'source' => 'attribute_entity',
                'reference' => 'currency',
            ],
            [
                'type' => 'mapping',
                'parent' => null,
                'entity_class' => ArrayEntity::class,
                'entity_name' => 'attribute_entity_order',
                'fields' => [
                    [
                        'class' => FkField::class,
                        'translated' => false,
                        'args' => [
                            'attribute_entity_id',
                            'attributeEntityId',
                            'attribute_entity',
                        ],
                        'flags' => [
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                    ],
                    [
                        'class' => FkField::class,
                        'translated' => false,
                        'args' => [
                            'order_id',
                            'orderId',
                            'order',
                        ],
                        'flags' => [
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                    ],
                    [
                        'class' => ManyToOneAssociationField::class,
                        'translated' => false,
                        'args' => [
                            'attributeEntity',
                            'attribute_entity_id',
                            'attribute_entity',
                            'id',
                        ],
                        'flags' => [],
                    ],
                    [
                        'class' => ManyToOneAssociationField::class,
                        'translated' => false,
                        'args' => [
                            'order',
                            'order_id',
                            'order',
                            'id',
                        ],
                        'flags' => [],
                    ],
                ],
                'source' => 'attribute_entity',
                'reference' => 'order',
            ],
            [
                'type' => 'mapping',
                'parent' => null,
                'entity_class' => ArrayEntity::class,
                'entity_name' => 'my_own_mapping_table_name',
                'fields' => [
                    [
                        'class' => FkField::class,
                        'translated' => false,
                        'args' => [
                            'attribute_entity_id',
                            'attributeEntityId',
                            'attribute_entity',
                        ],
                        'flags' => [
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                    ],
                    [
                        'class' => FkField::class,
                        'translated' => false,
                        'args' => [
                            'product_id',
                            'productId',
                            'product',
                        ],
                        'flags' => [
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                    ],
                    [
                        'class' => ManyToOneAssociationField::class,
                        'translated' => false,
                        'args' => [
                            'attributeEntity',
                            'attribute_entity_id',
                            'attribute_entity',
                            'id',
                        ],
                        'flags' => [],
                    ],
                    [
                        'class' => ManyToOneAssociationField::class,
                        'translated' => false,
                        'args' => [
                            'product',
                            'product_id',
                            'product',
                            'id',
                        ],
                        'flags' => [],
                    ],
                ],
                'source' => 'attribute_entity',
                'reference' => 'product',
            ],
            [
                'type' => 'entity',
                'since' => '6.6.3.0',
                'parent' => null,
                'entity_class' => AttributeEntity::class,
                'entity_name' => 'attribute_entity',
                'hydrator_class' => EntityHydrator::class,
                'collection_class' => AttributeEntityCollection::class,
                'fields' => [
                    [
                        'type' => FieldType::UUID,
                        'name' => 'id',
                        'class' => IdField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                            PrimaryKey::class => [
                                'class' => PrimaryKey::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'id',
                            'id',
                        ],
                    ],
                    [
                        'type' => FieldType::STRING,
                        'name' => 'string',
                        'class' => StringField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'string',
                            'string',
                            255,
                        ],
                    ],
                    [
                        'type' => FieldType::TEXT,
                        'name' => 'text',
                        'class' => LongTextField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'text',
                            'text',
                        ],
                    ],
                    [
                        'type' => FieldType::INT,
                        'name' => 'int',
                        'class' => IntField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'int',
                            'int',
                        ],
                    ],
                    [
                        'type' => FieldType::FLOAT,
                        'name' => 'float',
                        'class' => FloatField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'float',
                            'float',
                        ],
                    ],
                    [
                        'type' => FieldType::BOOL,
                        'name' => 'bool',
                        'class' => BoolField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'bool',
                            'bool',
                        ],
                    ],
                    [
                        'type' => FieldType::DATETIME,
                        'name' => 'datetime',
                        'class' => DateTimeField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'datetime',
                            'datetime',
                        ],
                    ],
                    [
                        'type' => AutoIncrement::TYPE,
                        'name' => 'autoIncrement',
                        'class' => AutoIncrementField::class,
                        'flags' => [
                            ApiAware::class => [
                                'class' => ApiAware::class,
                                'args' => [],
                            ],
                        ],
                        'translated' => false,
                        'args' => [],
                    ],
                    [
                        'type' => FieldType::ENUM,
                        'name' => 'enum',
                        'class' => EnumField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'enum',
                            'enum',
                            StringEnum::A,
                        ],
                    ],
                    [
                        'type' => FieldType::JSON,
                        'name' => 'json',
                        'class' => JsonField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'json',
                            'json',
                        ],
                    ],
                    [
                        'type' => FieldType::DATE,
                        'name' => 'date',
                        'class' => DateField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'date',
                            'date',
                        ],
                    ],
                    [
                        'type' => FieldType::DATE_INTERVAL,
                        'name' => 'dateInterval',
                        'class' => DateIntervalField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'date_interval',
                            'dateInterval',
                        ],
                    ],
                    [
                        'type' => FieldType::TIME_ZONE,
                        'name' => 'timeZone',
                        'class' => TimeZoneField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'time_zone',
                            'timeZone',
                        ],
                    ],
                    [
                        'type' => Serialized::TYPE,
                        'name' => 'serialized',
                        'class' => SerializedField::class,
                        'flags' => [
                            ApiAware::class => [
                                'class' => ApiAware::class,
                                'args' => [],
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'serialized',
                            'serialized',
                            PriceFieldSerializer::class,
                        ],
                    ],
                    [
                        'type' => FieldType::PRICE,
                        'name' => 'price',
                        'class' => PriceField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'price',
                            'price',
                        ],
                    ],
                    [
                        'type' => FieldType::STRING,
                        'name' => 'transString',
                        'class' => StringField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                        ],
                        'translated' => true,
                        'args' => [
                            'trans_string',
                            'transString',
                            255,
                        ],
                    ],
                    [
                        'type' => FieldType::TEXT,
                        'name' => 'transText',
                        'class' => LongTextField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_text',
                            'transText',
                        ],
                    ],
                    [
                        'type' => FieldType::INT,
                        'name' => 'transInt',
                        'class' => IntField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_int',
                            'transInt',
                        ],
                    ],
                    [
                        'type' => FieldType::FLOAT,
                        'name' => 'transFloat',
                        'class' => FloatField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_float',
                            'transFloat',
                        ],
                    ],
                    [
                        'type' => FieldType::BOOL,
                        'name' => 'transBool',
                        'class' => BoolField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_bool',
                            'transBool',
                        ],
                    ],
                    [
                        'type' => FieldType::DATETIME,
                        'name' => 'transDatetime',
                        'class' => DateTimeField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_datetime',
                            'transDatetime',
                        ],
                    ],
                    [
                        'type' => FieldType::JSON,
                        'name' => 'transJson',
                        'class' => JsonField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_json',
                            'transJson',
                        ],
                    ],
                    [
                        'type' => FieldType::DATE,
                        'name' => 'transDate',
                        'class' => DateField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_date',
                            'transDate',
                        ],
                    ],
                    [
                        'type' => FieldType::DATE_INTERVAL,
                        'name' => 'transDateInterval',
                        'class' => DateIntervalField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_date_interval',
                            'transDateInterval',
                        ],
                    ],
                    [
                        'type' => FieldType::TIME_ZONE,
                        'name' => 'transTimeZone',
                        'class' => TimeZoneField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'trans_time_zone',
                            'transTimeZone',
                        ],
                    ],
                    [
                        'type' => FieldType::STRING,
                        'name' => 'differentName',
                        'class' => StringField::class,
                        'flags' => [],
                        'translated' => true,
                        'args' => [
                            'another_column_name',
                            'differentName',
                            255,
                        ],
                    ],
                    [
                        'type' => ForeignKey::TYPE,
                        'name' => 'currencyId',
                        'class' => FkField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'currency_id',
                            'currencyId',
                            'currency',
                        ],
                    ],
                    [
                        'type' => State::TYPE,
                        'name' => 'stateId',
                        'class' => StateMachineStateField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'state_id',
                            'stateId',
                            'order.state',
                            [
                                'system',
                            ],
                        ],
                    ],
                    [
                        'type' => FieldType::STRING,
                        'name' => 'emptyString',
                        'class' => StringField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                            AllowEmptyString::class => [
                                'class' => AllowEmptyString::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'empty_string',
                            'emptyString',
                            255,
                        ],
                    ],
                    [
                        'type' => ForeignKey::TYPE,
                        'name' => 'followId',
                        'class' => FkField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'follow_id',
                            'followId',
                            'currency',
                        ],
                    ],
                    [
                        'type' => ManyToOne::TYPE,
                        'name' => 'currency',
                        'class' => ManyToOneAssociationField::class,
                        'flags' => [
                            'cascade' => [
                                'class' => RestrictDelete::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'currency',
                            'currency_id',
                            'currency',
                            'id',
                        ],
                    ],
                    [
                        'type' => OneToOne::TYPE,
                        'name' => 'follow',
                        'class' => OneToOneAssociationField::class,
                        'flags' => [
                            'cascade' => [
                                'class' => SetNullOnDelete::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'follow',
                            'follow_id',
                            'id',
                            'currency',
                            false,
                        ],
                    ],
                    [
                        'type' => ManyToOne::TYPE,
                        'name' => 'state',
                        'class' => ManyToOneAssociationField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'state',
                            'state_id',
                            'state_machine_state',
                            'id',
                        ],
                    ],
                    [
                        'type' => OneToMany::TYPE,
                        'name' => 'aggs',
                        'class' => OneToManyAssociationField::class,
                        'flags' => [
                            AsArray::class => [
                                'class' => AsArray::class,
                            ],
                            'cascade' => [
                                'class' => CascadeDelete::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'aggs',
                            'attribute_entity_agg',
                            'attribute_entity_id',
                            'id',
                        ],
                    ],
                    [
                        'type' => ManyToMany::TYPE,
                        'name' => 'currencies',
                        'class' => ManyToManyAssociationField::class,
                        'flags' => [
                            AsArray::class => [
                                'class' => AsArray::class,
                            ],
                            'cascade' => [
                                'class' => CascadeDelete::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'currencies',
                            'currency',
                            'attribute_entity_currency',
                            'attribute_entity_id',
                            'currency_id',
                        ],
                    ],
                    [
                        'type' => ManyToMany::TYPE,
                        'name' => 'orders',
                        'class' => ManyToManyAssociationField::class,
                        'flags' => [
                            AsArray::class => [
                                'class' => AsArray::class,
                            ],
                            'cascade' => [
                                'class' => CascadeDelete::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'orders',
                            'order',
                            'attribute_entity_order',
                            'attribute_entity_id',
                            'order_id',
                        ],
                    ],
                    [
                        'type' => Translations::TYPE,
                        'name' => 'translations',
                        'class' => TranslationsAssociationField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                            ApiAware::class => [
                                'class' => ApiAware::class,
                                'args' => [],
                            ],
                            AsArray::class => [
                                'class' => AsArray::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'attribute_entity_translation',
                            'attribute_entity_id',
                        ],
                    ],
                    [
                        'type' => ManyToMany::TYPE,
                        'name' => 'ownMapping',
                        'class' => ManyToManyAssociationField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                            AsArray::class => [
                                'class' => AsArray::class,
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'ownMapping',
                            'product',
                            'my_own_mapping_table_name',
                            'attribute_entity_id',
                            'product_id',
                        ],
                    ],
                    [
                        'type' => FieldType::STRING,
                        'name' => 'htmlString',
                        'class' => StringField::class,
                        'flags' => [
                            Required::class => [
                                'class' => Required::class,
                            ],
                            AllowHtml::class => [
                                'class' => AllowHtml::class,
                                'args' => [
                                    'sanitized' => false,
                                ],
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'html_string',
                            'htmlString',
                            255,
                        ],
                    ],
                    [
                        'type' => FieldType::EMAIL,
                        'name' => 'email',
                        'class' => EmailField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'email',
                            'email',
                            255,
                        ],
                    ],
                    [
                        'type' => FieldType::STRING,
                        'name' => 'longString',
                        'class' => StringField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'long_string',
                            'longString',
                            4096,
                        ],
                    ],
                    [
                        'type' => Password::TYPE,
                        'name' => 'password',
                        'class' => PasswordField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'password',
                            'password',
                            \PASSWORD_DEFAULT,
                            [],
                            'customer',
                        ],
                    ],
                    [
                        'type' => ListFieldAttr::TYPE,
                        'name' => 'tags',
                        'class' => ListField::class,
                        'flags' => [],
                        'translated' => false,
                        'args' => [
                            'tags',
                            'tags',
                            StringField::class,
                        ],
                    ],
                    [
                        'type' => CustomFieldsAttr::TYPE,
                        'name' => 'customFields',
                        'class' => CustomFields::class,
                        'flags' => [
                            ApiAware::class => [
                                'class' => ApiAware::class,
                                'args' => [],
                            ],
                        ],
                        'translated' => false,
                        'args' => [
                            'custom_fields',
                            'customFields',
                        ],
                    ],
                ],
            ],
        ];
    }
}
