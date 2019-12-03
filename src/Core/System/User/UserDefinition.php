<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
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
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;

class UserDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'user';

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

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new Required()),
            new FkField('avatar_id', 'avatarId', MediaDefinition::class),
            (new StringField('username', 'username'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new PasswordField('password', 'password'))->addFlags(new Required(), new ReadProtected(SalesChannelApiSource::class, AdminApiSource::class)),
            (new StringField('first_name', 'firstName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new BoolField('admin', 'admin'),
            new CustomFields(),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false),
            new OneToOneAssociationField('avatarMedia', 'avatar_id', 'id', MediaDefinition::class),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'user_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('accessKeys', UserAccessKeyDefinition::class, 'user_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'user_id', 'id'),
            (new OneToManyAssociationField('importExportLogEntries', ImportExportLogDefinition::class, 'user_id', 'id'))->addFlags(new SetNullOnDelete()),

            (new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, AclUserRoleDefinition::class, 'user_id', 'acl_role_id'))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),

            (new OneToOneAssociationField('recoveryUser', 'id', 'user_id', UserRecoveryDefinition::class, false))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),

            (new StringField('store_token', 'storeToken'))->addFlags(new ReadProtected(SalesChannelApiSource::class, AdminApiSource::class)),
        ]);
    }
}
