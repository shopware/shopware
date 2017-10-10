<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ConfigFormFieldTranslationWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Locale\Writer\Resource\LocaleWriteResource;

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
        $this->fields['configFormField'] = new ReferenceField('configFormFieldUuid', 'uuid', ConfigFormFieldWriteResource::class);
        $this->fields['configFormFieldUuid'] = (new FkField('config_form_field_uuid', ConfigFormFieldWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', LocaleWriteResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ConfigFormFieldWriteResource::class,
            LocaleWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ConfigFormFieldTranslationWrittenEvent
    {
        $event = new ConfigFormFieldTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ConfigFormFieldWriteResource::class])) {
            $event->addEvent(ConfigFormFieldWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[LocaleWriteResource::class])) {
            $event->addEvent(LocaleWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
