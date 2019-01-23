<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;

class UserDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'user';
    }

    public static function getCollectionClass(): string
    {
        return UserCollection::class;
    }

    public static function getEntityClass(): string
    {
        return UserEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new Required()),
            (new StringField('username', 'username'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new PasswordField('password', 'password'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new DateField('last_login', 'lastLogin'),
            new BoolField('active', 'active'),
            new IntField('failed_logins', 'failedLogins'),
            new DateField('locked_until', 'lockedUntil'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, false),
            new OneToManyAssociationField('media', MediaDefinition::class, 'user_id', false, 'id'),
            new OneToManyAssociationField('accessKeys', UserAccessKeyDefinition::class, 'user_id', false, 'id'),
        ]);
    }
}
