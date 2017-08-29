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

class CoreConfigMailsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_config_mails');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['frommail'] = (new StringField('frommail'))->setFlags(new Required());
        $this->fields['fromname'] = (new StringField('fromname'))->setFlags(new Required());
        $this->fields['subject'] = (new StringField('subject'))->setFlags(new Required());
        $this->fields['content'] = (new LongTextField('content'))->setFlags(new Required());
        $this->fields['contentHTML'] = (new LongTextField('contentHTML'))->setFlags(new Required());
        $this->fields['ishtml'] = (new IntField('ishtml'))->setFlags(new Required());
        $this->fields['attachment'] = (new StringField('attachment'))->setFlags(new Required());
        $this->fields['mailtype'] = new IntField('mailtype');
        $this->fields['context'] = new LongTextField('context');
        $this->fields['dirty'] = new IntField('dirty');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreConfigMailsResource::class
        ];
    }
}
