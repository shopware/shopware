<?php declare(strict_types=1);

namespace Shopware\Product\Gateway\Resource;

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

class ProductNotificationResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_notification');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['orderNumber'] = (new StringField('order_number'))->setFlags(new Required());
        $this->fields['createdAt'] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields['mail'] = (new StringField('mail'))->setFlags(new Required());
        $this->fields['send'] = (new IntField('send'))->setFlags(new Required());
        $this->fields['language'] = (new StringField('language'))->setFlags(new Required());
        $this->fields['shopLink'] = (new StringField('shop_link'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductNotificationResource::class
        ];
    }
}
