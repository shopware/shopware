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

class EmarketingBannersResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_emarketing_banners');
        
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['validFrom'] = new DateField('valid_from');
        $this->fields['validTo'] = new DateField('valid_to');
        $this->fields['img'] = (new StringField('img'))->setFlags(new Required());
        $this->fields['link'] = (new StringField('link'))->setFlags(new Required());
        $this->fields['linkTarget'] = (new StringField('link_target'))->setFlags(new Required());
        $this->fields['categoryID'] = new IntField('categoryID');
        $this->fields['extension'] = (new StringField('extension'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\EmarketingBannersResource::class
        ];
    }
}
