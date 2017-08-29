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

class ExportResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_export');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['lastExport'] = (new DateField('last_export'))->setFlags(new Required());
        $this->fields['active'] = new BoolField('active');
        $this->fields['hash'] = (new StringField('hash'))->setFlags(new Required());
        $this->fields['show'] = new IntField('show');
        $this->fields['countArticles'] = (new IntField('count_articles'))->setFlags(new Required());
        $this->fields['expiry'] = (new DateField('expiry'))->setFlags(new Required());
        $this->fields['interval'] = (new IntField('interval'))->setFlags(new Required());
        $this->fields['formatID'] = new IntField('formatID');
        $this->fields['lastChange'] = (new DateField('last_change'))->setFlags(new Required());
        $this->fields['filename'] = (new StringField('filename'))->setFlags(new Required());
        $this->fields['encodingID'] = new IntField('encodingID');
        $this->fields['categoryID'] = new IntField('categoryID');
        $this->fields['currencyID'] = new IntField('currencyID');
        $this->fields['customergroupID'] = new IntField('customergroupID');
        $this->fields['partnerID'] = new StringField('partnerID');
        $this->fields['languageID'] = new IntField('languageID');
        $this->fields['activeFilter'] = new IntField('active_filter');
        $this->fields['imageFilter'] = new IntField('image_filter');
        $this->fields['stockminFilter'] = new IntField('stockmin_filter');
        $this->fields['instockFilter'] = (new IntField('instock_filter'))->setFlags(new Required());
        $this->fields['priceFilter'] = (new FloatField('price_filter'))->setFlags(new Required());
        $this->fields['ownFilter'] = (new LongTextField('own_filter'))->setFlags(new Required());
        $this->fields['header'] = (new LongTextField('header'))->setFlags(new Required());
        $this->fields['body'] = (new LongTextField('body'))->setFlags(new Required());
        $this->fields['footer'] = (new LongTextField('footer'))->setFlags(new Required());
        $this->fields['countFilter'] = (new IntField('count_filter'))->setFlags(new Required());
        $this->fields['multishopID'] = new IntField('multishopID');
        $this->fields['variantExport'] = new IntField('variant_export');
        $this->fields['cacheRefreshed'] = new DateField('cache_refreshed');
        $this->fields['dirty'] = new IntField('dirty');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\ExportResource::class
        ];
    }
}
