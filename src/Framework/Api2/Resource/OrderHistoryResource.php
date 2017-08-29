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

class OrderHistoryResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order_history');
        
        $this->fields['orderID'] = (new IntField('orderID'))->setFlags(new Required());
        $this->fields['userID'] = new IntField('userID');
        $this->fields['previousOrderStatusId'] = new IntField('previous_order_status_id');
        $this->fields['orderStatusId'] = new IntField('order_status_id');
        $this->fields['previousPaymentStatusId'] = new IntField('previous_payment_status_id');
        $this->fields['paymentStatusId'] = new IntField('payment_status_id');
        $this->fields['comment'] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields['changeDate'] = new DateField('change_date');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderHistoryResource::class
        ];
    }
}
