<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class AttributeConfigurationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const TABLE_NAME_FIELD = 'tableName';
    protected const COLUMN_NAME_FIELD = 'columnName';
    protected const COLUMN_TYPE_FIELD = 'columnType';
    protected const DEFAULT_VALUE_FIELD = 'defaultValue';
    protected const POSITION_FIELD = 'position';
    protected const TRANSLATABLE_FIELD = 'translatable';
    protected const DISPLAY_IN_BACKEND_FIELD = 'displayInBackend';
    protected const CUSTOM_FIELD = 'custom';
    protected const HELP_TEXT_FIELD = 'helpText';
    protected const SUPPORT_TEXT_FIELD = 'supportText';
    protected const LABEL_FIELD = 'label';
    protected const ENTITY_FIELD = 'entity';
    protected const ARRAY_STORE_FIELD = 'arrayStore';

    public function __construct()
    {
        parent::__construct('attribute_configuration');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TABLE_NAME_FIELD] = (new StringField('table_name'))->setFlags(new Required());
        $this->fields[self::COLUMN_NAME_FIELD] = (new StringField('column_name'))->setFlags(new Required());
        $this->fields[self::COLUMN_TYPE_FIELD] = (new StringField('column_type'))->setFlags(new Required());
        $this->fields[self::DEFAULT_VALUE_FIELD] = new StringField('default_value');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::TRANSLATABLE_FIELD] = (new BoolField('translatable'))->setFlags(new Required());
        $this->fields[self::DISPLAY_IN_BACKEND_FIELD] = (new BoolField('display_in_backend'))->setFlags(new Required());
        $this->fields[self::CUSTOM_FIELD] = (new BoolField('custom'))->setFlags(new Required());
        $this->fields[self::HELP_TEXT_FIELD] = new LongTextField('help_text');
        $this->fields[self::SUPPORT_TEXT_FIELD] = new StringField('support_text');
        $this->fields[self::LABEL_FIELD] = new StringField('label');
        $this->fields[self::ENTITY_FIELD] = new StringField('entity');
        $this->fields[self::ARRAY_STORE_FIELD] = new LongTextField('array_store');
        $this->fields[self::HELP_TEXT_FIELD] = new TranslatedField('helpText', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::SUPPORT_TEXT_FIELD] = new TranslatedField('supportText', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(\Shopware\Framework\Write\Resource\AttributeConfigurationTranslationResource::class, 'languageUuid');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\AttributeConfigurationResource::class,
            \Shopware\Framework\Write\Resource\AttributeConfigurationTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\AttributeConfigurationWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\AttributeConfigurationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\AttributeConfigurationResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Framework\Write\Resource\AttributeConfigurationTranslationResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
