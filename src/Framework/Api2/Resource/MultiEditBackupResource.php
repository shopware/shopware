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

class MultiEditBackupResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_multi_edit_backup');
        
        $this->fields['filterString'] = (new LongTextField('filter_string'))->setFlags(new Required());
        $this->fields['operationString'] = (new LongTextField('operation_string'))->setFlags(new Required());
        $this->fields['items'] = (new IntField('items'))->setFlags(new Required());
        $this->fields['date'] = new DateField('date');
        $this->fields['size'] = (new IntField('size'))->setFlags(new Required());
        $this->fields['path'] = (new StringField('path'))->setFlags(new Required());
        $this->fields['hash'] = (new StringField('hash'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\MultiEditBackupResource::class
        ];
    }
}
