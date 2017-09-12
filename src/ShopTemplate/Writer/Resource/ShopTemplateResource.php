<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

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

class ShopTemplateResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const TEMPLATE_FIELD = 'template';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const AUTHOR_FIELD = 'author';
    protected const LICENSE_FIELD = 'license';
    protected const ESI_FIELD = 'esi';
    protected const STYLE_SUPPORT_FIELD = 'styleSupport';
    protected const EMOTION_FIELD = 'emotion';
    protected const VERSION_FIELD = 'version';
    protected const PLUGIN_ID_FIELD = 'pluginId';
    protected const PARENT_ID_FIELD = 'parentId';

    public function __construct()
    {
        parent::__construct('shop_template');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new StringField('description');
        $this->fields[self::AUTHOR_FIELD] = new StringField('author');
        $this->fields[self::LICENSE_FIELD] = new StringField('license');
        $this->fields[self::ESI_FIELD] = (new BoolField('esi'))->setFlags(new Required());
        $this->fields[self::STYLE_SUPPORT_FIELD] = (new BoolField('style_support'))->setFlags(new Required());
        $this->fields[self::EMOTION_FIELD] = (new BoolField('emotion'))->setFlags(new Required());
        $this->fields[self::VERSION_FIELD] = (new IntField('version'))->setFlags(new Required());
        $this->fields[self::PLUGIN_ID_FIELD] = new IntField('plugin_id');
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', \Shopware\Framework\Write\Resource\PluginResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', \Shopware\Framework\Write\Resource\PluginResource::class, 'uuid'));
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class, 'uuid'));
        $this->fields['s'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class);
        $this->fields['configForms'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormResource::class);
        $this->fields['configFormFields'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class);
        $this->fields['configPresets'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigPresetResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\PluginResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigPresetResource::class
        ];
    }
}
