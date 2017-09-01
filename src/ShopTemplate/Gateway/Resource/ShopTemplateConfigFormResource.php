<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Gateway\Resource;

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

class ShopTemplateConfigFormResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const SHOP_TEMPLATE_ID_FIELD = 'shopTemplateId';
    protected const TYPE_FIELD = 'type';
    protected const NAME_FIELD = 'name';
    protected const TITLE_FIELD = 'title';
    protected const ATTRIBUTES_FIELD = 'attributes';

    public function __construct()
    {
        parent::__construct('shop_template_config_form');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::SHOP_TEMPLATE_ID_FIELD] = (new IntField('shop_template_id'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::ATTRIBUTES_FIELD] = new LongTextField('attributes');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateConfigFormResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateConfigFormResource::class, 'uuid'));
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['s'] = new SubresourceField(\Shopware\ShopTemplate\Gateway\Resource\ShopTemplateConfigFormResource::class);
        $this->fields['fields'] = new SubresourceField(\Shopware\ShopTemplate\Gateway\Resource\ShopTemplateConfigFormFieldResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateConfigFormResource::class,
            \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateResource::class,
            \Shopware\ShopTemplate\Gateway\Resource\ShopTemplateConfigFormFieldResource::class
        ];
    }
}
