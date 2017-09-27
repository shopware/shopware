<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopFormFieldTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const NOTE_FIELD = 'note';
    protected const LABEL_FIELD = 'label';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('shop_form_field_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::NOTE_FIELD] = new StringField('note');
        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new StringField('value'))->setFlags(new Required());
        $this->fields['shopFormField'] = new ReferenceField('shopFormFieldUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopFormFieldResource::class);
        $this->primaryKeyFields['shopFormFieldUuid'] = (new FkField('shop_form_field_uuid', \Shopware\Shop\Writer\Resource\ShopFormFieldResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopFormFieldResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Shop\Writer\Resource\ShopFormFieldTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopFormFieldTranslationWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopFormFieldTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormFieldResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormFieldResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopFormFieldTranslationResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopFormFieldTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
