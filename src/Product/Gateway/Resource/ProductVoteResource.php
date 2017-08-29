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

class ProductVoteResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_vote');
        
        $this->fields['productId'] = (new IntField('product_id'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['headline'] = (new StringField('headline'))->setFlags(new Required());
        $this->fields['comment'] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields['points'] = (new FloatField('points'))->setFlags(new Required());
        $this->fields['createdAt'] = new DateField('created_at');
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['answer'] = (new LongTextField('answer'))->setFlags(new Required());
        $this->fields['answerAt'] = new DateField('answer_at');
        $this->fields['shopId'] = new IntField('shop_id');
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productUuid'] = (new StringField('product_uuid'))->setFlags(new Required());
        $this->fields['shopUuid'] = (new StringField('shop_uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductVoteResource::class
        ];
    }
}
