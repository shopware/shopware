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

class CmsSupportResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_cms_support');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['text'] = (new LongTextField('text'))->setFlags(new Required());
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['emailTemplate'] = (new LongTextField('email_template'))->setFlags(new Required());
        $this->fields['emailSubject'] = (new StringField('email_subject'))->setFlags(new Required());
        $this->fields['text2'] = (new LongTextField('text2'))->setFlags(new Required());
        $this->fields['metaTitle'] = new StringField('meta_title');
        $this->fields['metaKeywords'] = new StringField('meta_keywords');
        $this->fields['metaDescription'] = new LongTextField('meta_description');
        $this->fields['ticketTypeID'] = (new IntField('ticket_typeID'))->setFlags(new Required());
        $this->fields['isocode'] = new StringField('isocode');
        $this->fields['shopIds'] = new StringField('shop_ids');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CmsSupportResource::class
        ];
    }
}
