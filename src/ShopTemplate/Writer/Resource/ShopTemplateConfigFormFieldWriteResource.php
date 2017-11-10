<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShopTemplate\Event\ShopTemplateConfigFormFieldWrittenEvent;

class ShopTemplateConfigFormFieldWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TYPE_FIELD = 'type';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';
    protected const DEFAULT_VALUE_FIELD = 'defaultValue';
    protected const SELECTION_FIELD = 'selection';
    protected const FIELD_LABEL_FIELD = 'fieldLabel';
    protected const SUPPORT_TEXT_FIELD = 'supportText';
    protected const ALLOW_BLANK_FIELD = 'allowBlank';
    protected const ATTRIBUTES_FIELD = 'attributes';
    protected const LESS_COMPATIBLE_FIELD = 'lessCompatible';

    public function __construct()
    {
        parent::__construct('shop_template_config_form_field');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::DEFAULT_VALUE_FIELD] = new LongTextField('default_value');
        $this->fields[self::SELECTION_FIELD] = new LongTextField('selection');
        $this->fields[self::FIELD_LABEL_FIELD] = new StringField('field_label');
        $this->fields[self::SUPPORT_TEXT_FIELD] = new StringField('support_text');
        $this->fields[self::ALLOW_BLANK_FIELD] = new BoolField('allow_blank');
        $this->fields[self::ATTRIBUTES_FIELD] = new LongTextField('attributes');
        $this->fields[self::LESS_COMPATIBLE_FIELD] = new BoolField('less_compatible');
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', ShopTemplateWriteResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shopTemplateConfigForm'] = new ReferenceField('shopTemplateConfigFormUuid', 'uuid', ShopTemplateConfigFormWriteResource::class);
        $this->fields['shopTemplateConfigFormUuid'] = (new FkField('shop_template_config_form_uuid', ShopTemplateConfigFormWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['values'] = new SubresourceField(ShopTemplateConfigFormFieldValueWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ShopTemplateWriteResource::class,
            ShopTemplateConfigFormWriteResource::class,
            self::class,
            ShopTemplateConfigFormFieldValueWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopTemplateConfigFormFieldWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ShopTemplateConfigFormFieldWrittenEvent($uuids, $context, $rawData, $errors);

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
