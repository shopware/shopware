<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Event\ShopFormFieldTranslationWrittenEvent;

class ShopFormFieldTranslationWriteResource extends WriteResource
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
        $this->fields['shopFormField'] = new ReferenceField('shopFormFieldUuid', 'uuid', ShopFormFieldWriteResource::class);
        $this->primaryKeyFields['shopFormFieldUuid'] = (new FkField('shop_form_field_uuid', ShopFormFieldWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShopFormFieldWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopFormFieldTranslationWrittenEvent
    {
        $event = new ShopFormFieldTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
