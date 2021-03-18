<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;

class EntityMapper
{
    public const PRICE_FIELD = [
        'type' => 'object',
        'properties' => [
            'gross' => self::FLOAT_FIELD,
            'net' => self::FLOAT_FIELD,
            'linked' => self::BOOLEAN_FIELD,
        ],
    ];

    public const DATE_FIELD = [
        'type' => 'date',
        'format' => 'yyyy-MM-dd HH:mm:ss.SSS',
        'ignore_malformed' => true,
    ];

    public const KEYWORD_FIELD = [
        'type' => 'keyword',
        'normalizer' => 'sw_lowercase_normalizer',
    ];
    public const BOOLEAN_FIELD = ['type' => 'boolean'];
    public const FLOAT_FIELD = ['type' => 'double'];
    public const INT_FIELD = ['type' => 'long'];

    public function mapField(EntityDefinition $definition, Field $field, Context $context): ?array
    {
        switch (true) {
            case $field instanceof TranslationsAssociationField:
                return null;

            case $field instanceof ManyToManyAssociationField:
                return [
                    'type' => 'nested',
                    'properties' => $this->mapFields($field->getToManyReferenceDefinition(), $context),
                ];
            case $field instanceof ManyToOneAssociationField:
            case $field instanceof OneToManyAssociationField:
            case $field instanceof OneToOneAssociationField:
                return [
                    'type' => 'nested',
                    'properties' => $this->mapFields($field->getReferenceDefinition(), $context),
                ];

            case $field instanceof BlobField:
                return null;

            case $field instanceof ListField:
                return self::KEYWORD_FIELD;

            case $field instanceof ParentAssociationField:
            case $field instanceof ChildrenAssociationField:
                return null;

            case $field instanceof BoolField:
                return self::BOOLEAN_FIELD;

            case $field instanceof FloatField:
                return self::FLOAT_FIELD;

            case $field instanceof ChildCountField:
            case $field instanceof IntField:
                return self::INT_FIELD;

            case $field instanceof ObjectField:
                return ['type' => 'object', 'dynamic' => true];

            case $field instanceof PriceField:
                return $this->createPriceField($context);

            case $field instanceof CustomFields:
                return ['type' => 'object', 'dynamic' => true];
            case $field instanceof JsonField:
                if (empty($field->getPropertyMapping())) {
                    return ['type' => 'object', 'dynamic' => true];
                }
                $properties = [];
                foreach ($field->getPropertyMapping() as $nested) {
                    $properties[$nested->getPropertyName()] = $this->mapField($definition, $nested, $context);
                }

                return ['type' => 'object', 'properties' => $properties];

            case $field instanceof LongTextField:
                return $this->createLongTextField();

            case $field instanceof TranslatedField:
                $reference = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);

                return $this->mapField($definition, $reference, $context);

            case $field instanceof UpdatedAtField:
            case $field instanceof CreatedAtField:
            case $field instanceof DateField:
                return self::DATE_FIELD;

            case $field instanceof StringField:
                return $this->createStringField();
            case $field instanceof PasswordField:
            case $field instanceof FkField:
            case $field instanceof IdField:
            case $field instanceof VersionField:
            case $field instanceof ParentFkField:
            case $field instanceof ReferenceVersionField:
                return self::KEYWORD_FIELD;

            default:
                return null;
        }
    }

    public function mapFields(EntityDefinition $definition, Context $context): array
    {
        $properties = [];
        $translated = [];

        $fields = $definition->getFields()->filter(static fn (Field $field) => !$field instanceof AssociationField);

        foreach ($fields as $field) {
            $fieldMapping = $this->mapField($definition, $field, $context);

            if ($fieldMapping === null) {
                continue;
            }

            if ($field instanceof TranslatedField) {
                $translated[$field->getPropertyName()] = $fieldMapping;
            }

            $properties[$field->getPropertyName()] = $fieldMapping;
        }

        if (!empty($translated)) {
            $properties['translated'] = [
                'type' => 'object',
                'properties' => $translated,
            ];
        }

        return $properties;
    }

    protected function createStringField(): array
    {
        return self::KEYWORD_FIELD;
    }

    protected function createLongTextField(): array
    {
        return ['type' => 'text'];
    }

    private function createPriceField(Context $context): array
    {
        $currencies = $context->getExtension('currencies');

        if (!$currencies instanceof EntityCollection) {
            return [
                'type' => 'object',
                'properties' => ['c_' . Defaults::CURRENCY => self::PRICE_FIELD],
            ];
        }
        $fields = [];

        foreach ($currencies as $currency) {
            $field = 'c_' . $currency->getId();

            $fields[$field] = self::PRICE_FIELD;
        }

        return [
            'type' => 'object',
            'properties' => $fields,
        ];
    }
}
