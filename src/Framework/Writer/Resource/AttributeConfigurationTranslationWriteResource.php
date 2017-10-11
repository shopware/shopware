<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\AttributeConfigurationTranslationWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class AttributeConfigurationTranslationWriteResource extends WriteResource
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
        $this->fields['attributeConfiguration'] = new ReferenceField('attributeConfigurationUuid', 'uuid', AttributeConfigurationWriteResource::class);
        $this->primaryKeyFields['attributeConfigurationUuid'] = (new FkField('attribute_configuration_uuid', AttributeConfigurationWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            AttributeConfigurationWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): AttributeConfigurationTranslationWrittenEvent
    {
        $event = new AttributeConfigurationTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
