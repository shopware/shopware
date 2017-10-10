<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ShippingMethod\Event\ShippingMethodTranslationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ShippingMethodTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const COMMENT_FIELD = 'comment';

    public function __construct()
    {
        parent::__construct('shipping_method_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::COMMENT_FIELD] = new StringField('comment');
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', ShippingMethodWriteResource::class);
        $this->primaryKeyFields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShippingMethodWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShippingMethodTranslationWrittenEvent
    {
        $event = new ShippingMethodTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ShippingMethodWriteResource::class])) {
            $event->addEvent(ShippingMethodWriteResource::createWrittenEvent($updates, $context));
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
