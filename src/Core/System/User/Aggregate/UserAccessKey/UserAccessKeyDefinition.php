<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

class UserAccessKeyDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'user_access_key';
    }

    public static function getCollectionClass(): string
    {
        return UserAccessKeyCollection::class;
    }

    public static function getEntityClass(): string
    {
        return UserAccessKeyEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return UserDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new Required()),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required()),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->addFlags(new Required()),
            new BoolField('write_access', 'writeAccess'),
            new DateField('last_usage_at', 'lastUsageAt'),
            new CustomFields(),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
