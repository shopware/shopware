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

class EmarketingBannersStatisticsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_emarketing_banners_statistics');
        
        $this->fields['bannerID'] = (new IntField('bannerID'))->setFlags(new Required());
        $this->fields['displayDate'] = (new DateField('display_date'))->setFlags(new Required());
        $this->fields['clicks'] = (new IntField('clicks'))->setFlags(new Required());
        $this->fields['views'] = (new IntField('views'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\EmarketingBannersStatisticsResource::class
        ];
    }
}
