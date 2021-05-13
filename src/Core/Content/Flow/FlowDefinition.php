<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
class FlowDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'flow';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowEntity::class;
    }

    public function getDefaults(): array
    {
        return ['active' => false, 'priority' => 1];
    }

    public function since(): ?string
    {
        return '6.4.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name', 255))->addFlags(new Required()),
            (new StringField('event_name', 'eventName', 255))->addFlags(new Required()),
            new IntField('priority', 'priority'),
            new BoolField('active', 'active'),
            (new StringField('description', 'description', 500)),
            (new OneToManyAssociationField('flowSequences', FlowSequenceDefinition::class, 'flow_id', 'id'))->addFlags(new CascadeDelete()),
            new CustomFields(),
        ]);
    }
}
