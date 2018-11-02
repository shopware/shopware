<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TenantIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class IntegrationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'integration';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('label', 'label'))->setFlags(new Required()),
            (new StringField('access_key', 'accessKey'))->setFlags(new Required()),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->setFlags(new Required()),
            new BoolField('write_access', 'writeAccess'),
            new DateField('last_usage_at', 'lastUsageAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return IntegrationCollection::class;
    }

    public static function getStructClass(): string
    {
        return IntegrationStruct::class;
    }
}
