<?php declare(strict_types=1);

namespace Shopware\Shop\Gateway\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ShopFormFieldResource extends Resource
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
    protected const CREATED_AT_FIELD = 'createdAt';
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
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::TICKET_TASK_FIELD] = (new StringField('ticket_task'))->setFlags(new Required());
        $this->fields['shopForm'] = new ReferenceField('shopFormUuid', 'uuid', \Shopware\Shop\Gateway\Resource\ShopFormResource::class);
        $this->fields['shopFormUuid'] = (new FkField('shop_form_uuid', \Shopware\Shop\Gateway\Resource\ShopFormResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Gateway\Resource\ShopFormResource::class,
            \Shopware\Shop\Gateway\Resource\ShopFormFieldResource::class
        ];
    }    
    
    public function getDefaults(string $type): array {
        if($type === self::FOR_UPDATE) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if($type === self::FOR_INSERT) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
