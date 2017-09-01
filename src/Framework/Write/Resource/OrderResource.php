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

class OrderResource extends Resource
{
    protected const ORDERNUMBER_FIELD = 'ordernumber';
    protected const USERID_FIELD = 'userID';
    protected const INVOICE_AMOUNT_FIELD = 'invoiceAmount';
    protected const INVOICE_AMOUNT_NET_FIELD = 'invoiceAmountNet';
    protected const INVOICE_SHIPPING_FIELD = 'invoiceShipping';
    protected const INVOICE_SHIPPING_NET_FIELD = 'invoiceShippingNet';
    protected const ORDERTIME_FIELD = 'ordertime';
    protected const STATUS_FIELD = 'status';
    protected const CLEARED_FIELD = 'cleared';
    protected const PAYMENTID_FIELD = 'paymentID';
    protected const TRANSACTIONID_FIELD = 'transactionID';
    protected const COMMENT_FIELD = 'comment';
    protected const CUSTOMERCOMMENT_FIELD = 'customercomment';
    protected const INTERNALCOMMENT_FIELD = 'internalcomment';
    protected const NET_FIELD = 'net';
    protected const TAXFREE_FIELD = 'taxfree';
    protected const PARTNERID_FIELD = 'partnerID';
    protected const TEMPORARYID_FIELD = 'temporaryID';
    protected const REFERER_FIELD = 'referer';
    protected const CLEAREDDATE_FIELD = 'cleareddate';
    protected const TRACKINGCODE_FIELD = 'trackingcode';
    protected const LANGUAGE_FIELD = 'language';
    protected const DISPATCHID_FIELD = 'dispatchID';
    protected const CURRENCY_FIELD = 'currency';
    protected const CURRENCYFACTOR_FIELD = 'currencyFactor';
    protected const SUBSHOPID_FIELD = 'subshopID';
    protected const REMOTE_ADDR_FIELD = 'remoteAddr';
    protected const DEVICETYPE_FIELD = 'deviceType';

    public function __construct()
    {
        parent::__construct('s_order');
        
        $this->fields[self::ORDERNUMBER_FIELD] = new StringField('ordernumber');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::INVOICE_AMOUNT_FIELD] = new FloatField('invoice_amount');
        $this->fields[self::INVOICE_AMOUNT_NET_FIELD] = (new FloatField('invoice_amount_net'))->setFlags(new Required());
        $this->fields[self::INVOICE_SHIPPING_FIELD] = new FloatField('invoice_shipping');
        $this->fields[self::INVOICE_SHIPPING_NET_FIELD] = (new FloatField('invoice_shipping_net'))->setFlags(new Required());
        $this->fields[self::ORDERTIME_FIELD] = new DateField('ordertime');
        $this->fields[self::STATUS_FIELD] = new IntField('status');
        $this->fields[self::CLEARED_FIELD] = new IntField('cleared');
        $this->fields[self::PAYMENTID_FIELD] = new IntField('paymentID');
        $this->fields[self::TRANSACTIONID_FIELD] = (new StringField('transactionID'))->setFlags(new Required());
        $this->fields[self::COMMENT_FIELD] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields[self::CUSTOMERCOMMENT_FIELD] = (new LongTextField('customercomment'))->setFlags(new Required());
        $this->fields[self::INTERNALCOMMENT_FIELD] = (new LongTextField('internalcomment'))->setFlags(new Required());
        $this->fields[self::NET_FIELD] = (new IntField('net'))->setFlags(new Required());
        $this->fields[self::TAXFREE_FIELD] = (new IntField('taxfree'))->setFlags(new Required());
        $this->fields[self::PARTNERID_FIELD] = (new StringField('partnerID'))->setFlags(new Required());
        $this->fields[self::TEMPORARYID_FIELD] = (new StringField('temporaryID'))->setFlags(new Required());
        $this->fields[self::REFERER_FIELD] = (new LongTextField('referer'))->setFlags(new Required());
        $this->fields[self::CLEAREDDATE_FIELD] = new DateField('cleareddate');
        $this->fields[self::TRACKINGCODE_FIELD] = (new StringField('trackingcode'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::DISPATCHID_FIELD] = (new IntField('dispatchID'))->setFlags(new Required());
        $this->fields[self::CURRENCY_FIELD] = (new StringField('currency'))->setFlags(new Required());
        $this->fields[self::CURRENCYFACTOR_FIELD] = (new FloatField('currencyFactor'))->setFlags(new Required());
        $this->fields[self::SUBSHOPID_FIELD] = (new IntField('subshopID'))->setFlags(new Required());
        $this->fields[self::REMOTE_ADDR_FIELD] = (new StringField('remote_addr'))->setFlags(new Required());
        $this->fields[self::DEVICETYPE_FIELD] = new StringField('deviceType');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderResource::class
        ];
    }
}
