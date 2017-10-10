<?php declare(strict_types=1);

namespace Shopware\Album\Writer\Resource;

use Shopware\Album\Event\AlbumWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Media\Writer\Resource\MediaWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class AlbumWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';
    protected const CREATE_THUMBNAILS_FIELD = 'createThumbnails';
    protected const THUMBNAIL_SIZE_FIELD = 'thumbnailSize';
    protected const ICON_FIELD = 'icon';
    protected const THUMBNAIL_HIGH_DPI_FIELD = 'thumbnailHighDpi';
    protected const THUMBNAIL_QUALITY_FIELD = 'thumbnailQuality';
    protected const THUMBNAIL_HIGH_DPI_QUALITY_FIELD = 'thumbnailHighDpiQuality';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('album');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::CREATE_THUMBNAILS_FIELD] = new BoolField('create_thumbnails');
        $this->fields[self::THUMBNAIL_SIZE_FIELD] = new LongTextField('thumbnail_size');
        $this->fields[self::ICON_FIELD] = new StringField('icon');
        $this->fields[self::THUMBNAIL_HIGH_DPI_FIELD] = new BoolField('thumbnail_high_dpi');
        $this->fields[self::THUMBNAIL_QUALITY_FIELD] = new IntField('thumbnail_quality');
        $this->fields[self::THUMBNAIL_HIGH_DPI_QUALITY_FIELD] = new IntField('thumbnail_high_dpi_quality');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(AlbumTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['parent'] = new SubresourceField(self::class);
        $this->fields['media'] = new SubresourceField(MediaWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            AlbumTranslationWriteResource::class,
            MediaWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): AlbumWrittenEvent
    {
        $event = new AlbumWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[AlbumTranslationWriteResource::class])) {
            $event->addEvent(AlbumTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[MediaWriteResource::class])) {
            $event->addEvent(MediaWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
