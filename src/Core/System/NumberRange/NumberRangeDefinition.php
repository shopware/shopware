<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;

#[Package('checkout')]
class NumberRangeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'number_range';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('type_id', 'typeId', NumberRangeTypeDefinition::class))->addFlags(new Required()),
            (new BoolField('global', 'global'))->addFlags(new Required()),
            new TranslatedField('name'),
            new TranslatedField('description'),
            (new StringField('pattern', 'pattern'))->addFlags(new Required()),
            (new IntField('start', 'start'))->addFlags(new Required()),
            new TranslatedField('customFields'),

            (new ManyToOneAssociationField('type', 'type_id', NumberRangeTypeDefinition::class)),
            (new OneToManyAssociationField('numberRangeSalesChannels', NumberRangeSalesChannelDefinition::class, 'number_range_id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('state', 'id', 'number_range_id', NumberRangeStateDefinition::class, true))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(NumberRangeTranslationDefinition::class, 'number_range_id'))->addFlags(new Required()),
        ]);
    }
}
