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

class CmsSupportFieldsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_cms_support_fields');
        
        $this->fields['errorMsg'] = (new StringField('error_msg'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['note'] = new StringField('note');
        $this->fields['typ'] = (new StringField('typ'))->setFlags(new Required());
        $this->fields['required'] = (new IntField('required'))->setFlags(new Required());
        $this->fields['supportID'] = (new IntField('supportID'))->setFlags(new Required());
        $this->fields['label'] = (new StringField('label'))->setFlags(new Required());
        $this->fields['class'] = (new StringField('class'))->setFlags(new Required());
        $this->fields['value'] = (new StringField('value'))->setFlags(new Required());
        $this->fields['added'] = (new DateField('added'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['ticketTask'] = (new StringField('ticket_task'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CmsSupportFieldsResource::class
        ];
    }
}
