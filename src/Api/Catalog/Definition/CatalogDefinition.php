<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Catalog\Collection\CatalogBasicCollection;
use Shopware\Api\Catalog\Event\Catalog\CatalogDeletedEvent;
use Shopware\Api\Catalog\Event\Catalog\CatalogWrittenEvent;
use Shopware\Api\Catalog\Repository\CatalogRepository;
use Shopware\Api\Catalog\Struct\CatalogBasicStruct;

class CatalogDefinition extends EntityDefinition
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
        return 'catalog';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CatalogRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CatalogBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CatalogDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CatalogWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CatalogBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
