<?php declare(strict_types=1);

namespace Shopware\Unit\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Unit\Collection\UnitBasicCollection;
use Shopware\Unit\Collection\UnitDetailCollection;
use Shopware\Unit\Event\Unit\UnitWrittenEvent;
use Shopware\Unit\Repository\UnitRepository;
use Shopware\Unit\Struct\UnitBasicStruct;
use Shopware\Unit\Struct\UnitDetailStruct;

class UnitDefinition extends EntityDefinition
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
        return 'unit';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new TranslatedField(new StringField('short_code', 'shortCode')))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('products', ProductDefinition::class, 'unit_uuid', false, 'uuid'),
            (new TranslationsAssociationField('translations', UnitTranslationDefinition::class, 'unit_uuid', false, 'uuid'))->setFlags(new Required()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return UnitRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return UnitBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return UnitWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return UnitBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return UnitTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return UnitDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return UnitDetailCollection::class;
    }
}
