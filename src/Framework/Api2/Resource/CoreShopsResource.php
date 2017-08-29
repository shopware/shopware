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

class CoreShopsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_shops');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['mainId'] = new IntField('main_id');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['title'] = new StringField('title');
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['host'] = new StringField('host');
        $this->fields['basePath'] = new StringField('base_path');
        $this->fields['baseUrl'] = new StringField('base_url');
        $this->fields['hosts'] = (new LongTextField('hosts'))->setFlags(new Required());
        $this->fields['secure'] = (new IntField('secure'))->setFlags(new Required());
        $this->fields['templateId'] = new IntField('template_id');
        $this->fields['documentTemplateId'] = new IntField('document_template_id');
        $this->fields['categoryId'] = new IntField('category_id');
        $this->fields['localeId'] = new IntField('locale_id');
        $this->fields['currencyId'] = new IntField('currency_id');
        $this->fields['customerGroupId'] = new IntField('customer_group_id');
        $this->fields['fallbackId'] = new IntField('fallback_id');
        $this->fields['customerScope'] = (new IntField('customer_scope'))->setFlags(new Required());
        $this->fields['default'] = (new IntField('default'))->setFlags(new Required());
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['paymentId'] = (new IntField('payment_id'))->setFlags(new Required());
        $this->fields['dispatchId'] = (new IntField('dispatch_id'))->setFlags(new Required());
        $this->fields['countryId'] = (new IntField('country_id'))->setFlags(new Required());
        $this->fields['taxCalculationType'] = new StringField('tax_calculation_type');
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class,
            \Shopware\Framework\Api2\Resource\CoreShopsResource::class
        ];
    }
}
