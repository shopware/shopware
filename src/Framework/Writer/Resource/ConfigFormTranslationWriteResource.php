<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ConfigFormTranslationWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Locale\Writer\Resource\LocaleWriteResource;

class ConfigFormTranslationWriteResource extends WriteResource
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
        $this->fields['configForm'] = new ReferenceField('configFormUuid', 'uuid', ConfigFormWriteResource::class);
        $this->fields['configFormUuid'] = (new FkField('config_form_uuid', ConfigFormWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', LocaleWriteResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ConfigFormWriteResource::class,
            LocaleWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ConfigFormTranslationWrittenEvent
    {
        $event = new ConfigFormTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
