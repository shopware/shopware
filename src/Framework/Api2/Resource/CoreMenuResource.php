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

class CoreMenuResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_menu');
        
        $this->fields['parent'] = new IntField('parent');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['onclick'] = new StringField('onclick');
        $this->fields['class'] = new StringField('class');
        $this->fields['position'] = new IntField('position');
        $this->fields['active'] = new BoolField('active');
        $this->fields['pluginID'] = new IntField('pluginID');
        $this->fields['controller'] = new StringField('controller');
        $this->fields['shortcut'] = new StringField('shortcut');
        $this->fields['action'] = new StringField('action');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreMenuResource::class
        ];
    }
}
