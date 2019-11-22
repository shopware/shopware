<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BundleDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return '_test_bundle';
    }

    public function getEntityClass(): string
    {
        return BundleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BundleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new LongTextField('long_description', 'longDescription'),
            (new StringField('discount_type', 'discountType'))->addFlags(new Required()),
            (new FloatField('discount', 'discount'))->addFlags(new Required()),
        ]);
    }
}
