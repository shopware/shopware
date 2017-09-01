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

class ShoppingWorldComponentResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const X_TYPE_FIELD = 'xType';
    protected const CONVERT_FUNCTION_FIELD = 'convertFunction';
    protected const DESCRIPTION_FIELD = 'description';
    protected const TEMPLATE_FIELD = 'template';
    protected const CLS_FIELD = 'cls';
    protected const PLUGIN_ID_FIELD = 'pluginId';
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('shopping_world_component');
        
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::X_TYPE_FIELD] = (new StringField('x_type'))->setFlags(new Required());
        $this->fields[self::CONVERT_FUNCTION_FIELD] = new StringField('convert_function');
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::CLS_FIELD] = (new StringField('cls'))->setFlags(new Required());
        $this->fields[self::PLUGIN_ID_FIELD] = new IntField('plugin_id');
        $this->primaryKeyFields[self::UUID_FIELD] = new UuidField('uuid');
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', \Shopware\Framework\Write\Resource\PluginResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', \Shopware\Framework\Write\Resource\PluginResource::class, 'uuid'));
        $this->fields['fields'] = new SubresourceField(\Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\PluginResource::class,
            \Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::class,
            \Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::class
        ];
    }
}
