<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;

#[Package('checkout')]
class NumberRangeStateDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'number_range_state';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeStateCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeStateEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('number_range_id', 'numberRangeId', NumberRangeDefinition::class))->addFlags(new Required()),
            (new IntField('last_value', 'lastValue'))->addFlags(new Required()),

            (new OneToOneAssociationField('numberRange', 'number_range_id', 'id', NumberRangeDefinition::class, false))->addFlags(new RestrictDelete()),
        ]);
    }
}
