<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field\TestDefinition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\WriteProtected;

class WriteProtectedTranslatedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new Required(), new PrimaryKey()),
            (new TranslatedField(new StringField('protected', 'protected')))->setFlags(new WriteProtected('WriteProtected')),
            new TranslationsAssociationField('translations', WriteProtectedTranslationDefinition::class, 'wp_id', false, 'id'),
        ]);
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return WriteProtectedTranslationDefinition::class;
    }
}
