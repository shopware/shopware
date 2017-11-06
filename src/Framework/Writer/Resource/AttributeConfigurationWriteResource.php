<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\AttributeConfigurationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class AttributeConfigurationWriteResource extends WriteResource
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
        $this->fields[self::HELP_TEXT_FIELD] = new TranslatedField('helpText', ShopWriteResource::class, 'uuid');
        $this->fields[self::SUPPORT_TEXT_FIELD] = new TranslatedField('supportText', ShopWriteResource::class, 'uuid');
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(AttributeConfigurationTranslationWriteResource::class, 'languageUuid');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            AttributeConfigurationTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): AttributeConfigurationWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new AttributeConfigurationWrittenEvent($uuids, $context, $rawData, $errors);

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
