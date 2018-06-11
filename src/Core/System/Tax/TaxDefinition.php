<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;
use Shopware\Core\System\Tax\Collection\TaxBasicCollection;
use Shopware\Core\System\Tax\Collection\TaxDetailCollection;
use Shopware\Core\System\Tax\Event\TaxDeletedEvent;
use Shopware\Core\System\Tax\Event\TaxWrittenEvent;
use Shopware\Core\System\Tax\Struct\TaxBasicStruct;
use Shopware\Core\System\Tax\Struct\TaxDetailStruct;

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
