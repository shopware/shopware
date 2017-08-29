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

class OrderDocumentsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order_documents');
        
        $this->primaryKeyFields['iD'] = (new IntField('ID'))->setFlags(new Required());
        $this->fields['date'] = (new DateField('date'))->setFlags(new Required());
        $this->fields['type'] = (new IntField('type'))->setFlags(new Required());
        $this->fields['userID'] = (new IntField('userID'))->setFlags(new Required());
        $this->fields['orderID'] = (new IntField('orderID'))->setFlags(new Required());
        $this->fields['amount'] = (new FloatField('amount'))->setFlags(new Required());
        $this->fields['docID'] = (new IntField('docID'))->setFlags(new Required());
        $this->fields['hash'] = (new StringField('hash'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderDocumentsResource::class
        ];
    }
}
