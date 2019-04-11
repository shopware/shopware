<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

// TODO: Migrate to general settings when exists
class StoreSettingsDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'store_settings';
    }

    public static function getEntityClass(): string
    {
        return StoreSettingsEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return StoreSettingsCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('setting_key', 'key'),
            new StringField('setting_value', 'value'),
        ]);
    }
}
