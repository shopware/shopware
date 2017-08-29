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

class CoreTranslationsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_translations');
        
        $this->fields['objecttype'] = (new StringField('objecttype'))->setFlags(new Required());
        $this->fields['objectdata'] = (new LongTextField('objectdata'))->setFlags(new Required());
        $this->fields['objectkey'] = (new IntField('objectkey'))->setFlags(new Required());
        $this->fields['objectlanguage'] = (new StringField('objectlanguage'))->setFlags(new Required());
        $this->fields['dirty'] = new IntField('dirty');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreTranslationsResource::class
        ];
    }
}
