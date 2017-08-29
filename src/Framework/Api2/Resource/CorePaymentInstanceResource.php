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

class CorePaymentInstanceResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_payment_instance');
        
        $this->fields['paymentMeanId'] = new IntField('payment_mean_id');
        $this->fields['orderId'] = new IntField('order_id');
        $this->fields['userId'] = new IntField('user_id');
        $this->fields['firstname'] = new StringField('firstname');
        $this->fields['lastname'] = new StringField('lastname');
        $this->fields['address'] = new StringField('address');
        $this->fields['zipcode'] = new StringField('zipcode');
        $this->fields['city'] = new StringField('city');
        $this->fields['accountNumber'] = new StringField('account_number');
        $this->fields['accountHolder'] = new StringField('account_holder');
        $this->fields['bankName'] = new StringField('bank_name');
        $this->fields['bankCode'] = new StringField('bank_code');
        $this->fields['bic'] = new StringField('bic');
        $this->fields['iban'] = new StringField('iban');
        $this->fields['amount'] = new FloatField('amount');
        $this->fields['createdAt'] = (new DateField('created_at'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CorePaymentInstanceResource::class
        ];
    }
}
