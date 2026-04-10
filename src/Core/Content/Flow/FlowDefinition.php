<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class FlowDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'flow';

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
        return [
            'active' => false,
            'priority' => 1,
        ];
    }

    public function since(): ?string
    {
        return '6.4.6.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of flow.'),
            (new StringField('name', 'name', 255))->addFlags(new Required())->setDescription('Name of the flow.'),
            (new StringField('event_name', 'eventName', 255))->addFlags(new Required())->setDescription('Name of the event.'),
            (new IntField('priority', 'priority'))->setDescription('A numerical value to prioritize one of the flows from the list.'),
            (new BlobField('payload', 'payload'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('When the boolean value is `true`, the flow is no more available for usage.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the flow is available for selection.'),
            (new StringField('description', 'description', 500))->setDescription('A short description of the defined flow.'),
            (new OneToManyAssociationField('sequences', FlowSequenceDefinition::class, 'flow_id', 'id'))->addFlags(new CascadeDelete()),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new FkField('app_flow_event_id', 'appFlowEventId', AppFlowEventDefinition::class))->setDescription('Unique identity of app flow event.'),
            new ManyToOneAssociationField('appFlowEvent', 'app_flow_event_id', AppFlowEventDefinition::class, 'id', false),
        ]);
    }
}
