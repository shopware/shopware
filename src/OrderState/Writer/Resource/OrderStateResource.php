<?php declare(strict_types=1);

namespace Shopware\OrderState\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderStateResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';
    protected const TYPE_FIELD = 'type';
    protected const HAS_MAIL_FIELD = 'hasMail';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('order_state');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::HAS_MAIL_FIELD] = new BoolField('has_mail');
        $this->fields['mails'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailResource::class);
        $this->fields['orders'] = new SubresourceField(\Shopware\Order\Writer\Resource\OrderResource::class);
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\OrderState\Writer\Resource\OrderStateTranslationResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\MailResource::class,
            \Shopware\Order\Writer\Resource\OrderResource::class,
            \Shopware\OrderState\Writer\Resource\OrderStateResource::class,
            \Shopware\OrderState\Writer\Resource\OrderStateTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\OrderState\Event\OrderStateWrittenEvent
    {
        $event = new \Shopware\OrderState\Event\OrderStateWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Order\Writer\Resource\OrderResource::class])) {
            $event->addEvent(\Shopware\Order\Writer\Resource\OrderResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderState\Writer\Resource\OrderStateResource::class])) {
            $event->addEvent(\Shopware\OrderState\Writer\Resource\OrderStateResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderState\Writer\Resource\OrderStateTranslationResource::class])) {
            $event->addEvent(\Shopware\OrderState\Writer\Resource\OrderStateTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
