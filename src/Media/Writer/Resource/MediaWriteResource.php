<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Album\Writer\Resource\AlbumWriteResource;
use Shopware\Category\Writer\Resource\CategoryWriteResource;
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
use Shopware\Framework\Writer\Resource\BlogMediaWriteResource;
use Shopware\Framework\Writer\Resource\FilterValueWriteResource;
use Shopware\Framework\Writer\Resource\MailAttachmentWriteResource;
use Shopware\Framework\Writer\Resource\UserWriteResource;
use Shopware\Media\Event\MediaWrittenEvent;
use Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields['blogMedias'] = new SubresourceField(BlogMediaWriteResource::class);
        $this->fields['categories'] = new SubresourceField(CategoryWriteResource::class);
        $this->fields['filterValues'] = new SubresourceField(FilterValueWriteResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(MailAttachmentWriteResource::class);
        $this->fields['album'] = new ReferenceField('albumUuid', 'uuid', AlbumWriteResource::class);
        $this->fields['albumUuid'] = (new FkField('album_uuid', AlbumWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['user'] = new ReferenceField('userUuid', 'uuid', UserWriteResource::class);
        $this->fields['userUuid'] = (new FkField('user_uuid', UserWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(MediaTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['productMedias'] = new SubresourceField(ProductMediaWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            BlogMediaWriteResource::class,
            CategoryWriteResource::class,
            FilterValueWriteResource::class,
            MailAttachmentWriteResource::class,
            AlbumWriteResource::class,
            UserWriteResource::class,
            self::class,
            MediaTranslationWriteResource::class,
            ProductMediaWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MediaWrittenEvent
    {
        $event = new MediaWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
