<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationBasicCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationDetailCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationDeletedEvent;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationWrittenEvent;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct\TaxAreaRuleTranslationBasicStruct;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct\TaxAreaRuleTranslationDetailStruct;

class TaxAreaRuleTranslationDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'tax_area_rule_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('tax_area_rule_id', 'taxAreaRuleId', TaxAreaRuleDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(TaxAreaRuleDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('taxAreaRule', 'tax_area_rule_id', TaxAreaRuleDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return TaxAreaRuleTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return TaxAreaRuleTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return TaxAreaRuleTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return TaxAreaRuleTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return TaxAreaRuleTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return TaxAreaRuleTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return TaxAreaRuleTranslationDetailCollection::class;
    }
}
