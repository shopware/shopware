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

class CampaignsContainersResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_campaigns_containers');
        
        $this->fields['promotionID'] = new IntField('promotionID');
        $this->fields['value'] = (new StringField('value'))->setFlags(new Required());
        $this->fields['type'] = (new StringField('type'))->setFlags(new Required());
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['position'] = new IntField('position');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CampaignsContainersResource::class
        ];
    }
}
