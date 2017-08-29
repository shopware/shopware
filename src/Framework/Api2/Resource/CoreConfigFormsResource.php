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

class CoreConfigFormsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_config_forms');
        
        $this->fields['parentId'] = new IntField('parent_id');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['label'] = new StringField('label');
        $this->fields['description'] = new LongTextField('description');
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['pluginId'] = new IntField('plugin_id');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreConfigFormsResource::class
        ];
    }
}
