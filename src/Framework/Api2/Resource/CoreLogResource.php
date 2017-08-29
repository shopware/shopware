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

class CoreLogResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_log');
        
        $this->fields['type'] = (new StringField('type'))->setFlags(new Required());
        $this->fields['key'] = (new StringField('key'))->setFlags(new Required());
        $this->fields['text'] = (new LongTextField('text'))->setFlags(new Required());
        $this->fields['date'] = (new DateField('date'))->setFlags(new Required());
        $this->fields['user'] = (new StringField('user'))->setFlags(new Required());
        $this->fields['ipAddress'] = (new StringField('ip_address'))->setFlags(new Required());
        $this->fields['userAgent'] = (new StringField('user_agent'))->setFlags(new Required());
        $this->fields['value4'] = (new StringField('value4'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreLogResource::class
        ];
    }
}
