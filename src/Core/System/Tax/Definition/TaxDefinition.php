<?php declare(strict_types=1);

namespace Shopware\System\Tax\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Content\Product\Definition\ProductDefinition;
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
