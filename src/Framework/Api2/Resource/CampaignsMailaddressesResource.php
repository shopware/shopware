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

class CampaignsMailaddressesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_campaigns_mailaddresses');
        
        $this->fields['customer'] = (new IntField('customer'))->setFlags(new Required());
        $this->fields['groupID'] = (new IntField('groupID'))->setFlags(new Required());
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['lastmailing'] = (new IntField('lastmailing'))->setFlags(new Required());
        $this->fields['lastread'] = (new IntField('lastread'))->setFlags(new Required());
        $this->fields['added'] = new DateField('added');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CampaignsMailaddressesResource::class
        ];
    }
}
