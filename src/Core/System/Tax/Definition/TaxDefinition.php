<?php declare(strict_types=1);

namespace Shopware\System\Tax\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\System\Tax\Collection\TaxBasicCollection;
use Shopware\System\Tax\Collection\TaxDetailCollection;
use Shopware\System\Tax\Event\Tax\TaxDeletedEvent;
use Shopware\System\Tax\Event\Tax\TaxWrittenEvent;
use Shopware\System\Tax\Repository\TaxRepository;
use Shopware\System\Tax\Struct\TaxBasicStruct;
use Shopware\System\Tax\Struct\TaxDetailStruct;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FloatField('tax_rate', 'rate'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'tax_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('areaRules', TaxAreaRuleDefinition::class, 'tax_id', false, 'id'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return TaxDeletedEvent::class;
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
