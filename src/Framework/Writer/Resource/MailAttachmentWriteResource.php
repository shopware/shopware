<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\MailAttachmentWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Media\Writer\Resource\MediaWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class MailAttachmentWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('mail_attachment');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['mail'] = new ReferenceField('mailUuid', 'uuid', MailWriteResource::class);
        $this->fields['mailUuid'] = (new FkField('mail_uuid', MailWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', MediaWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            MailWriteResource::class,
            MediaWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MailAttachmentWrittenEvent
    {
        $event = new MailAttachmentWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[MailWriteResource::class])) {
            $event->addEvent(MailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[MediaWriteResource::class])) {
            $event->addEvent(MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
