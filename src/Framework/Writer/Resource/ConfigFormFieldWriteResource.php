<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ConfigFormFieldWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ConfigFormFieldWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const VALUE_FIELD = 'value';
    protected const LABEL_FIELD = 'label';
    protected const DESCRIPTION_FIELD = 'description';
    protected const TYPE_FIELD = 'type';
    protected const REQUIRED_FIELD = 'required';
    protected const POSITION_FIELD = 'position';
    protected const SCOPE_FIELD = 'scope';

    public function __construct()
    {
        parent::__construct('config_form_field');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = new LongTextField('value');
        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::REQUIRED_FIELD] = new BoolField('required');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::SCOPE_FIELD] = new IntField('scope');
        $this->fields['configForm'] = new ReferenceField('configFormUuid', 'uuid', ConfigFormWriteResource::class);
        $this->fields['configFormUuid'] = (new FkField('config_form_uuid', ConfigFormWriteResource::class, 'uuid'));
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(ConfigFormFieldTranslationWriteResource::class, 'languageUuid');
    }

    public function getWriteOrder(): array
    {
        return [
            ConfigFormWriteResource::class,
            self::class,
            ConfigFormFieldTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ConfigFormFieldWrittenEvent
    {
        $event = new ConfigFormFieldWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
