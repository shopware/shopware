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

class SchemaVersionResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_schema_version');
        
        $this->primaryKeyFields['version'] = (new IntField('version'))->setFlags(new Required());
        $this->fields['startDate'] = (new DateField('start_date'))->setFlags(new Required());
        $this->fields['completeDate'] = new DateField('complete_date');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['errorMsg'] = new StringField('error_msg');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\SchemaVersionResource::class
        ];
    }
}
