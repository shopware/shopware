<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ConfigFormTranslationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const LABEL_FIELD = 'label';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('config_form_translation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = new StringField('label');
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields['configForm'] = new ReferenceField('configFormUuid', 'uuid', \Shopware\Framework\Write\Resource\ConfigFormResource::class);
        $this->fields['configFormUuid'] = (new FkField('config_form_uuid', \Shopware\Framework\Write\Resource\ConfigFormResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ConfigFormResource::class,
            \Shopware\Locale\Writer\Resource\LocaleResource::class,
            \Shopware\Framework\Write\Resource\ConfigFormTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\ConfigFormTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ConfigFormTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
