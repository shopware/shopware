<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;

class MediaThumbnailDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_thumbnail';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
            (new IntField('width', 'width'))->setFlags(new Required(), new WriteProtected(MediaProtectionFlags::WRITE_THUMBNAILS)),
            (new IntField('height', 'height'))->setFlags(new Required(), new WriteProtected(MediaProtectionFlags::WRITE_THUMBNAILS)),
            (new BoolField('highDpi', 'highDpi'))->setFlags(new Required(), new WriteProtected(MediaProtectionFlags::WRITE_THUMBNAILS)),
            (new StringField('url', 'url'))->setFlags(new Deferred()),

            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return MediaThumbnailCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaThumbnailEntity::class;
    }

    public static function getRootEntity(): ?string
    {
        return MediaDefinition::class;
    }
}
