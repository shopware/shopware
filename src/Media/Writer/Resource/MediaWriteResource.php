<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
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

class MediaWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const FILE_NAME_FIELD = 'fileName';
    protected const MIME_TYPE_FIELD = 'mimeType';
    protected const FILE_SIZE_FIELD = 'fileSize';
    protected const META_DATA_FIELD = 'metaData';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('media');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::FILE_NAME_FIELD] = (new StringField('file_name'))->setFlags(new Required());
        $this->fields[self::MIME_TYPE_FIELD] = (new StringField('mime_type'))->setFlags(new Required());
        $this->fields[self::FILE_SIZE_FIELD] = (new IntField('file_size'))->setFlags(new Required());
        $this->fields[self::META_DATA_FIELD] = new LongTextField('meta_data');
        $this->fields['blogMedias'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogMediaWriteResource::class);
        $this->fields['categories'] = new SubresourceField(\Shopware\Category\Writer\Resource\CategoryWriteResource::class);
        $this->fields['filterValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterValueWriteResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailAttachmentWriteResource::class);
        $this->fields['album'] = new ReferenceField('albumUuid', 'uuid', \Shopware\Album\Writer\Resource\AlbumWriteResource::class);
        $this->fields['albumUuid'] = (new FkField('album_uuid', \Shopware\Album\Writer\Resource\AlbumWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['user'] = new ReferenceField('userUuid', 'uuid', \Shopware\Framework\Write\Resource\UserWriteResource::class);
        $this->fields['userUuid'] = (new FkField('user_uuid', \Shopware\Framework\Write\Resource\UserWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Media\Writer\Resource\MediaTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['productMedias'] = new SubresourceField(\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogMediaWriteResource::class,
            \Shopware\Category\Writer\Resource\CategoryWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterValueWriteResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentWriteResource::class,
            \Shopware\Album\Writer\Resource\AlbumWriteResource::class,
            \Shopware\Framework\Write\Resource\UserWriteResource::class,
            \Shopware\Media\Writer\Resource\MediaWriteResource::class,
            \Shopware\Media\Writer\Resource\MediaTranslationWriteResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Media\Event\MediaWrittenEvent
    {
        $event = new \Shopware\Media\Event\MediaWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogMediaWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\MailAttachmentWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailAttachmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Album\Writer\Resource\AlbumWriteResource::class])) {
            $event->addEvent(\Shopware\Album\Writer\Resource\AlbumWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\UserWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\UserWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaWriteResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
