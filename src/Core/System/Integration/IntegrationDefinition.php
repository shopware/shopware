<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class IntegrationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'integration';
    }

    public static function getCollectionClass(): string
    {
        return IntegrationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return IntegrationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('label', 'label'))->addFlags(new Required()),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required()),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->addFlags(new Required()),
            new BoolField('write_access', 'writeAccess'),
            new DateField('last_usage_at', 'lastUsageAt'),
            new AttributesField(),
        ]);
    }
}
