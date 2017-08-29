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

class CampaignsMailingsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_campaigns_mailings');
        
        $this->fields['datum'] = new DateField('datum');
        $this->fields['groups'] = (new LongTextField('groups'))->setFlags(new Required());
        $this->fields['subject'] = (new StringField('subject'))->setFlags(new Required());
        $this->fields['sendermail'] = (new StringField('sendermail'))->setFlags(new Required());
        $this->fields['sendername'] = (new StringField('sendername'))->setFlags(new Required());
        $this->fields['plaintext'] = (new IntField('plaintext'))->setFlags(new Required());
        $this->fields['templateID'] = new IntField('templateID');
        $this->fields['languageID'] = (new IntField('languageID'))->setFlags(new Required());
        $this->fields['status'] = new IntField('status');
        $this->fields['locked'] = new DateField('locked');
        $this->fields['recipients'] = (new IntField('recipients'))->setFlags(new Required());
        $this->fields['read'] = new IntField('read');
        $this->fields['clicked'] = new IntField('clicked');
        $this->fields['customergroup'] = (new StringField('customergroup'))->setFlags(new Required());
        $this->fields['publish'] = (new IntField('publish'))->setFlags(new Required());
        $this->fields['timedDelivery'] = new DateField('timed_delivery');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CampaignsMailingsResource::class
        ];
    }
}
