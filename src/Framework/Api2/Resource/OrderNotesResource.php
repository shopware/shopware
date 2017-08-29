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

class OrderNotesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order_notes');
        
        $this->fields['sUniqueID'] = (new StringField('sUniqueID'))->setFlags(new Required());
        $this->fields['userID'] = new IntField('userID');
        $this->fields['articlename'] = (new StringField('articlename'))->setFlags(new Required());
        $this->fields['articleID'] = new IntField('articleID');
        $this->fields['ordernumber'] = (new StringField('ordernumber'))->setFlags(new Required());
        $this->fields['datum'] = new DateField('datum');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderNotesResource::class
        ];
    }
}
