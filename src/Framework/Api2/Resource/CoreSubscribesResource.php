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

class CoreSubscribesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_subscribes');
        
        $this->fields['subscribe'] = (new StringField('subscribe'))->setFlags(new Required());
        $this->fields['type'] = (new IntField('type'))->setFlags(new Required());
        $this->fields['listener'] = (new StringField('listener'))->setFlags(new Required());
        $this->fields['pluginID'] = new IntField('pluginID');
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreSubscribesResource::class
        ];
    }
}
