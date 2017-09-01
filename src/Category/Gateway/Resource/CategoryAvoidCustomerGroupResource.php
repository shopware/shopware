<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

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

class CategoryAvoidCustomerGroupResource extends Resource
{
    

    public function __construct()
    {
        parent::__construct('category_avoid_customer_group');
        
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Gateway\Resource\CategoryResource::class,
            \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class,
            \Shopware\Category\Gateway\Resource\CategoryAvoidCustomerGroupResource::class
        ];
    }
}
