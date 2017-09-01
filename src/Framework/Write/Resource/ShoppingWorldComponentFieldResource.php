<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

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

class ShoppingWorldComponentFieldResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOPPING_WORLD_COMPONENT_ID_FIELD = 'shoppingWorldComponentId';
    protected const NAME_FIELD = 'name';
    protected const X_TYPE_FIELD = 'xType';
    protected const VALUE_TYPE_FIELD = 'valueType';
    protected const FIELD_LABEL_FIELD = 'fieldLabel';
    protected const SUPPORT_TEXT_FIELD = 'supportText';
    protected const HELP_TITLE_FIELD = 'helpTitle';
    protected const HELP_TEXT_FIELD = 'helpText';
    protected const STORE_FIELD = 'store';
    protected const DISPLAY_FIELD_FIELD = 'displayField';
    protected const VALUE_FIELD_FIELD = 'valueField';
    protected const DEFAULT_VALUE_FIELD = 'defaultValue';
    protected const ALLOW_BLANK_FIELD = 'allowBlank';
    protected const TRANSLATABLE_FIELD = 'translatable';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('shopping_world_component_field');
        
        $this->primaryKeyFields[self::UUID_FIELD] = new UuidField('uuid');
        $this->fields[self::SHOPPING_WORLD_COMPONENT_ID_FIELD] = (new IntField('shopping_world_component_id'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::X_TYPE_FIELD] = (new StringField('x_type'))->setFlags(new Required());
        $this->fields[self::VALUE_TYPE_FIELD] = (new StringField('value_type'))->setFlags(new Required());
        $this->fields[self::FIELD_LABEL_FIELD] = (new StringField('field_label'))->setFlags(new Required());
        $this->fields[self::SUPPORT_TEXT_FIELD] = (new StringField('support_text'))->setFlags(new Required());
        $this->fields[self::HELP_TITLE_FIELD] = (new StringField('help_title'))->setFlags(new Required());
        $this->fields[self::HELP_TEXT_FIELD] = (new LongTextField('help_text'))->setFlags(new Required());
        $this->fields[self::STORE_FIELD] = (new StringField('store'))->setFlags(new Required());
        $this->fields[self::DISPLAY_FIELD_FIELD] = (new StringField('display_field'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD_FIELD] = (new StringField('value_field'))->setFlags(new Required());
        $this->fields[self::DEFAULT_VALUE_FIELD] = (new StringField('default_value'))->setFlags(new Required());
        $this->fields[self::ALLOW_BLANK_FIELD] = (new BoolField('allow_blank'))->setFlags(new Required());
        $this->fields[self::TRANSLATABLE_FIELD] = new BoolField('translatable');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields['shoppingWorldComponent'] = new ReferenceField('shoppingWorldComponentUuid', 'uuid', \Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::class);
        $this->fields['shoppingWorldComponentUuid'] = (new FkField('shopping_world_component_uuid', \Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::class,
            \Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::class
        ];
    }
}
