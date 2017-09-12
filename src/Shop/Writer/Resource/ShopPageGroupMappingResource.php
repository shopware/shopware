<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

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

class ShopPageGroupMappingResource extends Resource
{
    protected const SHOP_ID_FIELD = 'shopId';
    protected const SHOP_PAGE_GROUP_ID_FIELD = 'shopPageGroupId';

    public function __construct()
    {
        parent::__construct('shop_page_group_mapping');
        
        $this->primaryKeyFields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->primaryKeyFields[self::SHOP_PAGE_GROUP_ID_FIELD] = (new IntField('shop_page_group_id'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shopPageGroup'] = new ReferenceField('shopPageGroupUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class);
        $this->fields['shopPageGroupUuid'] = (new FkField('shop_page_group_uuid', \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class
        ];
    }
}
