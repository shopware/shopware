<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

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

class CategoryAttributeResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('category_attribute');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['categoryUuid'] = (new StringField('category_uuid'))->setFlags(new Required());
        $this->fields['attribute1'] = new StringField('attribute1');
        $this->fields['attribute2'] = new StringField('attribute2');
        $this->fields['attribute3'] = new StringField('attribute3');
        $this->fields['attribute4'] = new StringField('attribute4');
        $this->fields['attribute5'] = new StringField('attribute5');
        $this->fields['attribute6'] = new StringField('attribute6');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Gateway\Resource\CategoryAttributeResource::class
        ];
    }
}
