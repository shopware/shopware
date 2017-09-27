<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MailAttachmentResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('mail_attachment');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['mail'] = new ReferenceField('mailUuid', 'uuid', \Shopware\Framework\Write\Resource\MailResource::class);
        $this->fields['mailUuid'] = (new FkField('mail_uuid', \Shopware\Framework\Write\Resource\MailResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\MailResource::class,
            \Shopware\Media\Writer\Resource\MediaResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\MailAttachmentWrittenEvent
    {
        $event = new \Shopware\Framework\Event\MailAttachmentWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailAttachmentResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailAttachmentResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
