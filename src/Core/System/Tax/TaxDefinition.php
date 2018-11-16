<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class TaxDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'tax';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FloatField('tax_rate', 'taxRate'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'tax_id', false, 'id'))->setFlags(new RestrictDelete(), new ReverseInherited('tax')),
            (new OneToManyAssociationField('productServices', ProductServiceDefinition::class, 'tax_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return TaxCollection::class;
    }

    public static function getStructClass(): string
    {
        return TaxStruct::class;
    }
}
