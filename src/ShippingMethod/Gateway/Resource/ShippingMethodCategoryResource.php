<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Gateway\Resource;

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

class ShippingMethodCategoryResource extends Resource
{
    protected const SHIPPING_METHOD_ID_FIELD = 'shippingMethodId';
    protected const CATEGORY_ID_FIELD = 'categoryId';

    public function __construct()
    {
        parent::__construct('shipping_method_category');
        
        $this->primaryKeyFields[self::SHIPPING_METHOD_ID_FIELD] = (new IntField('shipping_method_id'))->setFlags(new Required());
        $this->primaryKeyFields[self::CATEGORY_ID_FIELD] = (new IntField('category_id'))->setFlags(new Required());
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class,
            \Shopware\Category\Gateway\Resource\CategoryResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCategoryResource::class
        ];
    }
}
