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

class CrontabResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_crontab');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['action'] = (new StringField('action'))->setFlags(new Required());
        $this->fields['elementID'] = new IntField('elementID');
        $this->fields['data'] = (new LongTextField('data'))->setFlags(new Required());
        $this->fields['next'] = new DateField('next');
        $this->fields['start'] = new DateField('start');
        $this->fields['interval'] = (new IntField('interval'))->setFlags(new Required());
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['disableOnError'] = new BoolField('disable_on_error');
        $this->fields['end'] = new DateField('end');
        $this->fields['informTemplate'] = (new StringField('inform_template'))->setFlags(new Required());
        $this->fields['informMail'] = (new StringField('inform_mail'))->setFlags(new Required());
        $this->fields['pluginID'] = new IntField('pluginID');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CrontabResource::class
        ];
    }
}
