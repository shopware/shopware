<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class AttributeConfigurationTranslationResource extends Resource
{
    protected const HELP_TEXT_FIELD = 'helpText';
    protected const SUPPORT_TEXT_FIELD = 'supportText';
    protected const LABEL_FIELD = 'label';

    public function __construct()
    {
        parent::__construct('attribute_configuration_translation');

        $this->fields[self::HELP_TEXT_FIELD] = new LongTextField('help_text');
        $this->fields[self::SUPPORT_TEXT_FIELD] = new StringField('support_text');
        $this->fields[self::LABEL_FIELD] = new StringField('label');
        $this->fields['attributeConfiguration'] = new ReferenceField('attributeConfigurationUuid', 'uuid', \Shopware\Framework\Write\Resource\AttributeConfigurationResource::class);
        $this->primaryKeyFields['attributeConfigurationUuid'] = (new FkField('attribute_configuration_uuid', \Shopware\Framework\Write\Resource\AttributeConfigurationResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\AttributeConfigurationResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\AttributeConfigurationTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\AttributeConfigurationTranslationWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\AttributeConfigurationTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\AttributeConfigurationResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Framework\Write\Resource\AttributeConfigurationTranslationResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
