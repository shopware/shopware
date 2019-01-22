<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderTranslation;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class MediaFolderTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'media_folder_translation';
    }

    public static function getCollectionClass(): string
    {
        return MediaFolderTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaFolderTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return MediaFolderDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
        ]);
    }
}
