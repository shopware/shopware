<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\BlogMediaWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Media\Writer\Resource\MediaWriteResource;

class BlogMediaWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PREVIEW_FIELD = 'preview';

    public function __construct()
    {
        parent::__construct('blog_media');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PREVIEW_FIELD] = (new BoolField('preview'))->setFlags(new Required());
        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', BlogWriteResource::class);
        $this->fields['blogUuid'] = (new FkField('blog_uuid', BlogWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', MediaWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            BlogWriteResource::class,
            MediaWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): BlogMediaWrittenEvent
    {
        $event = new BlogMediaWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[BlogWriteResource::class])) {
            $event->addEvent(BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[MediaWriteResource::class])) {
            $event->addEvent(MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
