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

class FilterValueResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('filter_value');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['optionId'] = (new IntField('option_id'))->setFlags(new Required());
        $this->fields['optionUuid'] = (new StringField('option_uuid'))->setFlags(new Required());
        $this->fields['value'] = (new StringField('value'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['mediaId'] = new IntField('media_id');
        $this->fields['mediaUuid'] = new StringField('media_uuid');
        $this->fields['attributes'] = new SubresourceField(\Shopware\Framework\Api2\Resource\FilterValueAttributeResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\FilterValueResource::class,
            \Shopware\Framework\Api2\Resource\FilterValueAttributeResource::class
        ];
    }
}
