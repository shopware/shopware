<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;

#[Package('system-settings')]
class UserDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'timeZone' => 'UTC',
        ];
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new Required()),
            (new StringField('username', 'username'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_ADMIN))->removeFlag(ApiAware::class)->addFlags(new Required()),
            (new StringField('first_name', 'firstName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('title', 'title'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new BoolField('admin', 'admin'),
            new DateTimeField('last_updated_password_at', 'lastUpdatedPasswordAt'),
            (new TimeZoneField('time_zone', 'timeZone'))->addFlags(new Required()),
            new CustomFields(),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false),
            new FkField('avatar_id', 'avatarId', MediaDefinition::class),
            new ManyToOneAssociationField('avatarMedia', 'avatar_id', MediaDefinition::class),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'user_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('accessKeys', UserAccessKeyDefinition::class, 'user_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configs', UserConfigDefinition::class, 'user_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'user_id', 'id'),
            (new OneToManyAssociationField('importExportLogEntries', ImportExportLogDefinition::class, 'user_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, AclUserRoleDefinition::class, 'user_id', 'acl_role_id')),
            (new OneToOneAssociationField('recoveryUser', 'id', 'user_id', UserRecoveryDefinition::class, false)),
            (new StringField('store_token', 'storeToken'))->removeFlag(ApiAware::class),
            new OneToManyAssociationField('createdOrders', OrderDefinition::class, 'created_by_id', 'id'),
            new OneToManyAssociationField('updatedOrders', OrderDefinition::class, 'updated_by_id', 'id'),
            new OneToManyAssociationField('createdCustomers', CustomerDefinition::class, 'created_by_id', 'id'),
            new OneToManyAssociationField('updatedCustomers', CustomerDefinition::class, 'updated_by_id', 'id'),
        ]);
    }
}
