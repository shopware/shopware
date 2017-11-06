<?php declare(strict_types=1);

namespace Shopware\Album\Writer\Resource;

use Shopware\Album\Event\AlbumWrittenEvent;
use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
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
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new AlbumWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
