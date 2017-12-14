<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\Api\Unit\Collection\UnitTranslationBasicCollection;
use Shopware\Api\Unit\Collection\UnitTranslationDetailCollection;
use Shopware\Api\Unit\Event\UnitTranslation\UnitTranslationWrittenEvent;
use Shopware\Api\Unit\Repository\UnitTranslationRepository;
use Shopware\Api\Unit\Struct\UnitTranslationBasicStruct;
use Shopware\Api\Unit\Struct\UnitTranslationDetailStruct;

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
            (new FkField('unit_uuid', 'unitUuid', UnitDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('short_code', 'shortCode'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('unit', 'unit_uuid', UnitDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
