<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Definition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationBasicCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationDetailCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Event\UnitTranslationDeletedEvent;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Event\UnitTranslationWrittenEvent;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct\UnitTranslationBasicStruct;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct\UnitTranslationDetailStruct;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationRepository;
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

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('unit_id', 'unitId', UnitDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(UnitDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', \Shopware\Core\System\Language\LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('short_code', 'shortCode'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', \Shopware\Core\System\Language\LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return UnitTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return UnitTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return UnitTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return UnitTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return UnitTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return UnitTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return UnitTranslationDetailCollection::class;
    }
}
