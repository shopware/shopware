<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\System\Language\LanguageDefinition;

class MediaTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CatalogField(),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
            (new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false))->setFlags(new RestrictDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return MediaTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return MediaTranslationStruct::class;
    }
}
