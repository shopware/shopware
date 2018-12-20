<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'media_translation';
    }

    public static function getCollectionClass(): string
    {
        return MediaTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaTranslationEntity::class;
    }

    public static function getDefinitionClass(): string
    {
        return MediaDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('title', 'title'),
            new LongTextField('description', 'description'),
        ]);
    }
}
