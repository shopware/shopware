<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\PasswordField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;

class UserDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'user';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            (new StringField('username', 'username'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new PasswordField('password', 'password'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('email', 'email'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new DateField('last_login', 'lastLogin'),
            new BoolField('active', 'active'),
            new IntField('failed_logins', 'failedLogins'),
            new DateField('locked_until', 'lockedUntil'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, false),
            new OneToManyAssociationField('media', MediaDefinition::class, 'user_id', false, 'id'),
            new OneToManyAssociationField('accessKeys', UserAccessKeyDefinition::class, 'user_id', false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return UserCollection::class;
    }

    public static function getStructClass(): string
    {
        return UserStruct::class;
    }
}
