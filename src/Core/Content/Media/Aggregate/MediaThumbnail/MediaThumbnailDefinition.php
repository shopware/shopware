<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaThumbnailDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_thumbnail';
    }

    public static function getCollectionClass(): string
    {
        return MediaThumbnailCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaThumbnailEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return MediaDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new Required()),

            (new IntField('width', 'width'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('height', 'height'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('url', 'url'))->addFlags(new Deferred()),

            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            new AttributesField(),
        ]);
    }
}
