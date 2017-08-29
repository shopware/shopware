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

class EmarketingPartnerResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_emarketing_partner');
        
        $this->fields['idcode'] = (new StringField('idcode'))->setFlags(new Required());
        $this->fields['datum'] = (new DateField('datum'))->setFlags(new Required());
        $this->fields['company'] = (new StringField('company'))->setFlags(new Required());
        $this->fields['contact'] = (new StringField('contact'))->setFlags(new Required());
        $this->fields['street'] = (new StringField('street'))->setFlags(new Required());
        $this->fields['zipcode'] = (new StringField('zipcode'))->setFlags(new Required());
        $this->fields['city'] = (new StringField('city'))->setFlags(new Required());
        $this->fields['phone'] = (new StringField('phone'))->setFlags(new Required());
        $this->fields['fax'] = (new StringField('fax'))->setFlags(new Required());
        $this->fields['country'] = (new StringField('country'))->setFlags(new Required());
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['web'] = (new StringField('web'))->setFlags(new Required());
        $this->fields['profil'] = (new LongTextField('profil'))->setFlags(new Required());
        $this->fields['fix'] = new FloatField('fix');
        $this->fields['percent'] = new FloatField('percent');
        $this->fields['cookielifetime'] = new IntField('cookielifetime');
        $this->fields['active'] = new BoolField('active');
        $this->fields['userID'] = new IntField('userID');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\EmarketingPartnerResource::class
        ];
    }
}
