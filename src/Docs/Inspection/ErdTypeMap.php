<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlacklistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListingPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

class ErdTypeMap
{
    private static $fieldTypeMap = [
        CustomFields::class => 'customFields',
        BlacklistRuleField::class => 'blacklistRule',
        BlobField::class => 'blob',
        BoolField::class => 'bool',
        CalculatedPriceField::class => 'calculatedPrice',
        CartPriceField::class => 'cartPrice',
        ChildCountField::class => 'childCount',
        ChildrenAssociationField::class => 'childrenAssociation',
        CreatedAtField::class => 'createdAt',
        DateTimeField::class => 'dateTime',
        DateField::class => 'date',
        EmailField::class => 'email',
        FkField::class => 'foreignKey',
        FloatField::class => 'float',
        IdField::class => 'id',
        IntField::class => 'int',
        JsonField::class => 'json',
        ListField::class => 'list',
        LongTextField::class => 'longText',
        LongTextWithHtmlField::class => 'longTextWithHtml',
        ManyToManyAssociationField::class => 'manyToManyAssociation',
        ManyToOneAssociationField::class => 'manyToOneAssociation',
        ObjectField::class => 'object',
        OneToManyAssociationField::class => 'oneToManyAssociation',
        ParentAssociationField::class => 'parentAssociation',
        ParentFkField::class => 'parentFk',
        PasswordField::class => 'password',
        PriceDefinitionField::class => 'priceDefinition',
        PriceField::class => 'price',
        ReferenceVersionField::class => 'referenceVersion',
        StringField::class => 'string',
        TranslatedField::class => 'translated',
        TreeLevelField::class => 'treeLevel',
        TreePathField::class => 'treePath',
        UpdatedAtField::class => 'updatedAt',
        VersionDataPayloadField::class => 'versionDataPayload',
        VersionField::class => 'version',
        WhitelistRuleField::class => 'whitelistRule',
        TranslationsAssociationField::class => 'translationAssociation',
        OneToOneAssociationField::class => 'oneToOneAssociation',
        ListingPriceField::class => 'priceRulesJson',
        NumberRangeField::class => 'numberRange',
        ConfigJsonField::class => 'configurationValue',
        ManyToManyIdField::class => 'manyToManyId',
        LockedField::class => 'writeLockIndicator',
        SlotConfigField::class => 'configurationValue',
        StateMachineStateField::class => 'stateMachineState',
    ];

    public function map(Field $field): string
    {
        $fieldClass = \get_class($field);

        if (!isset(self::$fieldTypeMap[$fieldClass])) {
            throw new \InvalidArgumentException($fieldClass . ' not found');
        }

        return self::$fieldTypeMap[$fieldClass];
    }
}
