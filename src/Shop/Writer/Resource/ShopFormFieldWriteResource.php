<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Event\ShopFormFieldWrittenEvent;

class ShopFormFieldWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOP_FORM_ID_FIELD = 'shopFormId';
    protected const ERROR_MSG_FIELD = 'errorMsg';
    protected const NAME_FIELD = 'name';
    protected const NOTE_FIELD = 'note';
    protected const TYPE_FIELD = 'type';
    protected const REQUIRED_FIELD = 'required';
    protected const LABEL_FIELD = 'label';
    protected const CLASS_FIELD = 'class';
    protected const VALUE_FIELD = 'value';
    protected const POSITION_FIELD = 'position';
    protected const TICKET_TASK_FIELD = 'ticketTask';

    public function __construct()
    {
        parent::__construct('shop_form_field');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHOP_FORM_ID_FIELD] = (new IntField('shop_form_id'))->setFlags(new Required());
        $this->fields[self::ERROR_MSG_FIELD] = (new StringField('error_msg'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::NOTE_FIELD] = new StringField('note');
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::REQUIRED_FIELD] = (new BoolField('required'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields[self::CLASS_FIELD] = (new StringField('class'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new StringField('value'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::TICKET_TASK_FIELD] = (new StringField('ticket_task'))->setFlags(new Required());
        $this->fields['shopForm'] = new ReferenceField('shopFormUuid', 'uuid', ShopFormWriteResource::class);
        $this->fields['shopFormUuid'] = (new FkField('shop_form_uuid', ShopFormWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::NOTE_FIELD] = new TranslatedField('note', ShopWriteResource::class, 'uuid');
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', ShopWriteResource::class, 'uuid');
        $this->fields[self::VALUE_FIELD] = new TranslatedField('value', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ShopFormFieldTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShopFormWriteResource::class,
            self::class,
            ShopFormFieldTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopFormFieldWrittenEvent
    {
        $event = new ShopFormFieldWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
