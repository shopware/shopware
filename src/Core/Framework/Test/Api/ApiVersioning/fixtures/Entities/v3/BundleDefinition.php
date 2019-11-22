<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundlePrice\BundlePriceDefinition;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundleTanslation\BundleTranslationDefinition;

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

    public function getDefaults(): array
    {
        return [
            'pseudoPrice' => 0.0,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new TranslatedField('name'),
            new TranslatedField('translatedDescription'),
            (new LongTextField('description', 'description'))->addFlags(new Deprecated('v2', 'v3', 'translatedDescription')),
            (new BoolField('is_absolute', 'isAbsolute'))->addFlags(new Required()),
            (new FloatField('discount', 'discount'))->addFlags(new Required()),
            new FloatField('pseudo_price', 'pseudoPrice'),

            new TranslationsAssociationField(BundleTranslationDefinition::class, '_test_bundle_id'),

            new OneToManyAssociationField('prices', BundlePriceDefinition::class, 'bundle_id'),
        ]);
    }
}
