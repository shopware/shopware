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

class EmarketingVouchersResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_emarketing_vouchers');
        
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['vouchercode'] = (new StringField('vouchercode'))->setFlags(new Required());
        $this->fields['numberofunits'] = new IntField('numberofunits');
        $this->fields['value'] = new FloatField('value');
        $this->fields['minimumcharge'] = new FloatField('minimumcharge');
        $this->fields['shippingfree'] = new IntField('shippingfree');
        $this->fields['bindtosupplier'] = new IntField('bindtosupplier');
        $this->fields['validFrom'] = new DateField('valid_from');
        $this->fields['validTo'] = new DateField('valid_to');
        $this->fields['ordercode'] = (new StringField('ordercode'))->setFlags(new Required());
        $this->fields['modus'] = new IntField('modus');
        $this->fields['percental'] = (new IntField('percental'))->setFlags(new Required());
        $this->fields['numorder'] = (new IntField('numorder'))->setFlags(new Required());
        $this->fields['customergroup'] = new IntField('customergroup');
        $this->fields['restrictarticles'] = (new LongTextField('restrictarticles'))->setFlags(new Required());
        $this->fields['strict'] = (new IntField('strict'))->setFlags(new Required());
        $this->fields['subshopID'] = new IntField('subshopID');
        $this->fields['taxconfig'] = (new StringField('taxconfig'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\EmarketingVouchersResource::class
        ];
    }
}
