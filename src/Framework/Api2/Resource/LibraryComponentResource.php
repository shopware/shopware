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

class LibraryComponentResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_library_component');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['xType'] = (new StringField('x_type'))->setFlags(new Required());
        $this->fields['convertFunction'] = new StringField('convert_function');
        $this->fields['description'] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields['template'] = (new StringField('template'))->setFlags(new Required());
        $this->fields['cls'] = (new StringField('cls'))->setFlags(new Required());
        $this->fields['pluginID'] = new IntField('pluginID');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\LibraryComponentResource::class
        ];
    }
}
