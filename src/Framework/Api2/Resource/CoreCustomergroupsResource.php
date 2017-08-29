<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class CoreCustomergroupsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_customergroups');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['groupkey'] = (new StringField('groupkey'))->setFlags(new Required());
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['tax'] = new IntField('tax');
        $this->fields['taxinput'] = (new IntField('taxinput'))->setFlags(new Required());
        $this->fields['mode'] = (new IntField('mode'))->setFlags(new Required());
        $this->fields['discount'] = (new FloatField('discount'))->setFlags(new Required());
        $this->fields['minimumorder'] = (new FloatField('minimumorder'))->setFlags(new Required());
        $this->fields['minimumordersurcharge'] = (new FloatField('minimumordersurcharge'))->setFlags(new Required());
        $this->fields['productAvoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAvoidCustomerGroupResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductAvoidCustomerGroupResource::class,
            \Shopware\Framework\Api2\Resource\CoreCustomergroupsResource::class
        ];
    }
}
