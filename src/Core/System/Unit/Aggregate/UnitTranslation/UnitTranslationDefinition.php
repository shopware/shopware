<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationBasicCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationBasicStruct;
use Shopware\Core\System\Unit\UnitDefinition;

class UnitTranslationDefinition extends EntityDefinition
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
        return 'unit_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('unit_id', 'unitId', UnitDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(UnitDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', \Shopware\Core\System\Language\LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('short_code', 'shortCode'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', \Shopware\Core\System\Language\LanguageDefinition::class, false),
        ]);
    }

    public static function getBasicCollectionClass(): string
    {
        return UnitTranslationBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return UnitTranslationBasicStruct::class;
    }
}
