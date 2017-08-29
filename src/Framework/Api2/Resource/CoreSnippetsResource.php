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

class CoreSnippetsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_snippets');
        
        $this->fields['namespace'] = (new StringField('namespace'))->setFlags(new Required());
        $this->fields['shopID'] = (new IntField('shopID'))->setFlags(new Required());
        $this->fields['localeID'] = (new IntField('localeID'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['value'] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields['created'] = (new DateField('created'))->setFlags(new Required());
        $this->fields['updated'] = (new DateField('updated'))->setFlags(new Required());
        $this->fields['dirty'] = new IntField('dirty');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreSnippetsResource::class
        ];
    }
}
