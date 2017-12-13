<?php declare(strict_types=1);

namespace Shopware\Tax\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Tax\Collection\TaxBasicCollection;
use Shopware\Tax\Collection\TaxDetailCollection;
use Shopware\Tax\Event\Tax\TaxWrittenEvent;
use Shopware\Tax\Repository\TaxRepository;
use Shopware\Tax\Struct\TaxBasicStruct;
use Shopware\Tax\Struct\TaxDetailStruct;

class TaxDefinition extends EntityDefinition
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
        return 'tax';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FloatField('tax_rate', 'rate'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('products', ProductDefinition::class, 'tax_uuid', false, 'uuid'),
            new OneToManyAssociationField('areaRules', TaxAreaRuleDefinition::class, 'tax_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return TaxRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return TaxBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return TaxWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return TaxBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return TaxDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return TaxDetailCollection::class;
    }
}
