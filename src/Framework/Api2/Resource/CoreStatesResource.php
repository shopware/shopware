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

class CoreStatesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_states');
        
        $this->fields['name'] = new StringField('name');
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['group'] = (new StringField('group'))->setFlags(new Required());
        $this->fields['mail'] = (new IntField('mail'))->setFlags(new Required());
        $this->fields['coreConfigMailss'] = new SubresourceField(\Shopware\Framework\Api2\Resource\CoreConfigMailsResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreConfigMailsResource::class,
            \Shopware\Framework\Api2\Resource\CoreStatesResource::class
        ];
    }
}
