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

class CampaignsMailaddressesResource extends Resource
{
    protected const CUSTOMER_FIELD = 'customer';
    protected const GROUPID_FIELD = 'groupID';
    protected const EMAIL_FIELD = 'email';
    protected const LASTMAILING_FIELD = 'lastmailing';
    protected const LASTREAD_FIELD = 'lastread';
    protected const ADDED_FIELD = 'added';

    public function __construct()
    {
        parent::__construct('s_campaigns_mailaddresses');
        
        $this->fields[self::CUSTOMER_FIELD] = (new IntField('customer'))->setFlags(new Required());
        $this->fields[self::GROUPID_FIELD] = (new IntField('groupID'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::LASTMAILING_FIELD] = (new IntField('lastmailing'))->setFlags(new Required());
        $this->fields[self::LASTREAD_FIELD] = (new IntField('lastread'))->setFlags(new Required());
        $this->fields[self::ADDED_FIELD] = new DateField('added');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsMailaddressesResource::class
        ];
    }
}
