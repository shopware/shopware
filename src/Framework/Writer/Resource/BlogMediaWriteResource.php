<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class BlogMediaWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PREVIEW_FIELD = 'preview';

    public function __construct()
    {
        parent::__construct('blog_media');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PREVIEW_FIELD] = (new BoolField('preview'))->setFlags(new Required());
        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', \Shopware\Framework\Write\Resource\BlogWriteResource::class);
        $this->fields['blogUuid'] = (new FkField('blog_uuid', \Shopware\Framework\Write\Resource\BlogWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogWriteResource::class,
            \Shopware\Media\Writer\Resource\MediaWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogMediaWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\BlogMediaWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BlogMediaWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaWriteResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogMediaWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogMediaWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
