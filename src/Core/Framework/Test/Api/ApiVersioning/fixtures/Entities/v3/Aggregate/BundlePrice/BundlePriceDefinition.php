<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundlePrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleDefinition;

class BundlePriceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = '_test_bundle_price';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getDefaults(): array
    {
        return [
            'pseudoPrice' => 0.0,
        ];
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BundleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('bundle_id', 'bundleId', BundleDefinition::class))->addFlags(new Required()),
            (new PriceField('price', 'price'))->addFlags(new Required()),
            (new IntField('quantity_start', 'quantityStart'))->addFlags(new Required()),
            new IntField('quantity_end', 'quantityEnd'),
            new ManyToOneAssociationField('bundle', 'bundle_id', BundleDefinition::class, 'id', false),
        ]);
    }
}
