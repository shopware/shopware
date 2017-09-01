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

class PluginRecommendationsResource extends Resource
{
    protected const CATEGORYID_FIELD = 'categoryID';
    protected const BANNER_ACTIVE_FIELD = 'bannerActive';
    protected const NEW_ACTIVE_FIELD = 'newActive';
    protected const BOUGHT_ACTIVE_FIELD = 'boughtActive';
    protected const SUPPLIER_ACTIVE_FIELD = 'supplierActive';

    public function __construct()
    {
        parent::__construct('s_plugin_recommendations');
        
        $this->fields[self::CATEGORYID_FIELD] = (new IntField('categoryID'))->setFlags(new Required());
        $this->fields[self::BANNER_ACTIVE_FIELD] = (new IntField('banner_active'))->setFlags(new Required());
        $this->fields[self::NEW_ACTIVE_FIELD] = (new IntField('new_active'))->setFlags(new Required());
        $this->fields[self::BOUGHT_ACTIVE_FIELD] = (new IntField('bought_active'))->setFlags(new Required());
        $this->fields[self::SUPPLIER_ACTIVE_FIELD] = (new IntField('supplier_active'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\PluginRecommendationsResource::class
        ];
    }
}
