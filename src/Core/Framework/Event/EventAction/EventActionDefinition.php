<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class EventActionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'event_action';
    }

    public static function getCollectionClass(): string
    {
        return EventActionCollection::class;
    }

    public static function getEntityClass(): string
    {
        return EventActionEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('event_name', 'eventName', 500))->addFlags(new Required()),
            (new StringField('action_name', 'actionName', 500))->addFlags(new Required()),
            new JsonField('config', 'config'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
