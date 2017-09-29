<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ConfigFormFieldTranslationWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const LABEL_FIELD = 'label';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('config_form_field_translation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = new StringField('label');
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields['configFormField'] = new ReferenceField('configFormFieldUuid', 'uuid', \Shopware\Framework\Write\Resource\ConfigFormFieldWriteResource::class);
        $this->fields['configFormFieldUuid'] = (new FkField('config_form_field_uuid', \Shopware\Framework\Write\Resource\ConfigFormFieldWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ConfigFormFieldWriteResource::class,
            \Shopware\Locale\Writer\Resource\LocaleWriteResource::class,
            \Shopware\Framework\Write\Resource\ConfigFormFieldTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\ConfigFormFieldTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ConfigFormFieldTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleWriteResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
