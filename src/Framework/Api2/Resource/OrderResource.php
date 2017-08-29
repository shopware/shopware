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

class OrderResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order');
        
        $this->fields['ordernumber'] = new StringField('ordernumber');
        $this->fields['userID'] = new IntField('userID');
        $this->fields['invoiceAmount'] = new FloatField('invoice_amount');
        $this->fields['invoiceAmountNet'] = (new FloatField('invoice_amount_net'))->setFlags(new Required());
        $this->fields['invoiceShipping'] = new FloatField('invoice_shipping');
        $this->fields['invoiceShippingNet'] = (new FloatField('invoice_shipping_net'))->setFlags(new Required());
        $this->fields['ordertime'] = new DateField('ordertime');
        $this->fields['status'] = new IntField('status');
        $this->fields['cleared'] = new IntField('cleared');
        $this->fields['paymentID'] = new IntField('paymentID');
        $this->fields['transactionID'] = (new StringField('transactionID'))->setFlags(new Required());
        $this->fields['comment'] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields['customercomment'] = (new LongTextField('customercomment'))->setFlags(new Required());
        $this->fields['internalcomment'] = (new LongTextField('internalcomment'))->setFlags(new Required());
        $this->fields['net'] = (new IntField('net'))->setFlags(new Required());
        $this->fields['taxfree'] = (new IntField('taxfree'))->setFlags(new Required());
        $this->fields['partnerID'] = (new StringField('partnerID'))->setFlags(new Required());
        $this->fields['temporaryID'] = (new StringField('temporaryID'))->setFlags(new Required());
        $this->fields['referer'] = (new LongTextField('referer'))->setFlags(new Required());
        $this->fields['cleareddate'] = new DateField('cleareddate');
        $this->fields['trackingcode'] = (new StringField('trackingcode'))->setFlags(new Required());
        $this->fields['language'] = (new StringField('language'))->setFlags(new Required());
        $this->fields['dispatchID'] = (new IntField('dispatchID'))->setFlags(new Required());
        $this->fields['currency'] = (new StringField('currency'))->setFlags(new Required());
        $this->fields['currencyFactor'] = (new FloatField('currencyFactor'))->setFlags(new Required());
        $this->fields['subshopID'] = (new IntField('subshopID'))->setFlags(new Required());
        $this->fields['remoteAddr'] = (new StringField('remote_addr'))->setFlags(new Required());
        $this->fields['deviceType'] = new StringField('deviceType');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderResource::class
        ];
    }
}
