<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\BusinessAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BusinessActionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'business_action';
    }

    public static function getCollectionClass(): string
    {
        return BusinessActionCollection::class;
    }

    public static function getEntityClass(): string
    {
        return BusinessActionEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('technical_name', 'technicalName', 500))->addFlags(new Required()),
            new JsonField('need_available_data', 'needAvailableData'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
