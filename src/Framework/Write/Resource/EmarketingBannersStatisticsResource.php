<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class EmarketingBannersStatisticsResource extends Resource
{
    protected const BANNERID_FIELD = 'bannerID';
    protected const DISPLAY_DATE_FIELD = 'displayDate';
    protected const CLICKS_FIELD = 'clicks';
    protected const VIEWS_FIELD = 'views';

    public function __construct()
    {
        parent::__construct('s_emarketing_banners_statistics');
        
        $this->fields[self::BANNERID_FIELD] = (new IntField('bannerID'))->setFlags(new Required());
        $this->fields[self::DISPLAY_DATE_FIELD] = (new DateField('display_date'))->setFlags(new Required());
        $this->fields[self::CLICKS_FIELD] = (new IntField('clicks'))->setFlags(new Required());
        $this->fields[self::VIEWS_FIELD] = (new IntField('views'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\EmarketingBannersStatisticsResource::class
        ];
    }
}
