<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;

class ExtendedProductManufacturerDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'extended_product_manufacturer';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('name', 'name'),

            new FkField('manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class),
            new FkField('language_id', 'languageId', LanguageDefinition::class),

            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new OneToOneAssociationField('toOne', 'manufacturer_id', 'id', ProductManufacturerDefinition::class),
            new ManyToOneAssociationField('manyToOne', 'manufacturer_id', ProductManufacturerDefinition::class, 'id'),
        ]);
    }
}
