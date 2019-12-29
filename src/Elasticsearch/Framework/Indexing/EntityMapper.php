<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListingPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;

class EntityMapper
{
    public const PRICE_FIELD = [
        'type' => 'object',
        'properties' => [
            'gross' => ['type' => 'double'],
            'net' => ['type' => 'double'],
            'linked' => ['type' => 'boolean'],
        ],
    ];

    public const DATE_FIELD = [
        'type' => 'date',
        'format' => 'yyyy-MM-dd HH:mm:ss.SSS',
        'ignore_malformed' => true,
    ];

    public const KEYWORD_FIELD = ['type' => 'keyword'];

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
            case $field instanceof BlacklistRuleField:
            case $field instanceof WhitelistRuleField:
                return self::KEYWORD_FIELD;

            case $field instanceof ParentAssociationField:
            case $field instanceof ChildrenAssociationField:
                return null;

            case $field instanceof BoolField:
                return ['type' => 'boolean'];

            case $field instanceof FloatField:
                return ['type' => 'double'];

            case $field instanceof ChildCountField:
            case $field instanceof IntField:
                return ['type' => 'long'];

            case $field instanceof ObjectField:
                return ['type' => 'object'];

            case $field instanceof PriceField:
                return self::PRICE_FIELD;

            case $field instanceof ListingPriceField:
                return [
                    'type' => 'nested',
                    'properties' => [
                        'ruleId' => self::KEYWORD_FIELD,
                        'price' => self::PRICE_FIELD,
                        'createdAt' => self::DATE_FIELD,
                        'updatedAt' => self::DATE_FIELD,
                    ],
                ];

            case $field instanceof JsonField:
                if (empty($field->getPropertyMapping())) {
                    return ['type' => 'object'];
                }
                $properties = [];
                foreach ($field->getPropertyMapping() as $nested) {
                    $properties[$nested->getPropertyName()] = $this->mapField($definition, $nested, $context);
                }

                return ['type' => 'object', 'properties' => $properties];

            case $field instanceof LongTextField:
            case $field instanceof LongTextWithHtmlField:
                return ['type' => 'text'];

            case $field instanceof TranslatedField:
                $reference = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);

                return $this->mapField($definition, $reference, $context);

            case $field instanceof UpdatedAtField:
            case $field instanceof CreatedAtField:
            case $field instanceof DateField:
                return self::DATE_FIELD;

            case $field instanceof PasswordField:
            case $field instanceof StringField:
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

        $fields = $definition->getFields()->filter(static function (Field $field) {
            return !$field instanceof AssociationField;
        });

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
}
