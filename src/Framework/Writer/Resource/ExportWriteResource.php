<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ExportWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ExportWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const LAST_EXPORT_FIELD = 'lastExport';
    protected const ACTIVE_FIELD = 'active';
    protected const HASH_FIELD = 'hash';
    protected const SHOW_FIELD = 'show';
    protected const COUNT_ARTICLES_FIELD = 'countArticles';
    protected const EXPIRY_FIELD = 'expiry';
    protected const INTERVAL_FIELD = 'interval';
    protected const FORMATID_FIELD = 'formatID';
    protected const LAST_CHANGE_FIELD = 'lastChange';
    protected const FILENAME_FIELD = 'filename';
    protected const ENCODINGID_FIELD = 'encodingID';
    protected const CATEGORYID_FIELD = 'categoryID';
    protected const CURRENCYID_FIELD = 'currencyID';
    protected const CUSTOMERGROUPID_FIELD = 'customergroupID';
    protected const PARTNERID_FIELD = 'partnerID';
    protected const LANGUAGEID_FIELD = 'languageID';
    protected const ACTIVE_FILTER_FIELD = 'activeFilter';
    protected const IMAGE_FILTER_FIELD = 'imageFilter';
    protected const STOCKMIN_FILTER_FIELD = 'stockminFilter';
    protected const INSTOCK_FILTER_FIELD = 'instockFilter';
    protected const PRICE_FILTER_FIELD = 'priceFilter';
    protected const OWN_FILTER_FIELD = 'ownFilter';
    protected const HEADER_FIELD = 'header';
    protected const BODY_FIELD = 'body';
    protected const FOOTER_FIELD = 'footer';
    protected const COUNT_FILTER_FIELD = 'countFilter';
    protected const MULTISHOPID_FIELD = 'multishopID';
    protected const VARIANT_EXPORT_FIELD = 'variantExport';
    protected const CACHE_REFRESHED_FIELD = 'cacheRefreshed';
    protected const DIRTY_FIELD = 'dirty';

    public function __construct()
    {
        parent::__construct('s_export');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::LAST_EXPORT_FIELD] = (new DateField('last_export'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::HASH_FIELD] = (new StringField('hash'))->setFlags(new Required());
        $this->fields[self::SHOW_FIELD] = new IntField('show');
        $this->fields[self::COUNT_ARTICLES_FIELD] = (new IntField('count_articles'))->setFlags(new Required());
        $this->fields[self::EXPIRY_FIELD] = (new DateField('expiry'))->setFlags(new Required());
        $this->fields[self::INTERVAL_FIELD] = (new IntField('interval'))->setFlags(new Required());
        $this->fields[self::FORMATID_FIELD] = new IntField('formatID');
        $this->fields[self::LAST_CHANGE_FIELD] = (new DateField('last_change'))->setFlags(new Required());
        $this->fields[self::FILENAME_FIELD] = (new StringField('filename'))->setFlags(new Required());
        $this->fields[self::ENCODINGID_FIELD] = new IntField('encodingID');
        $this->fields[self::CATEGORYID_FIELD] = new IntField('categoryID');
        $this->fields[self::CURRENCYID_FIELD] = new IntField('currencyID');
        $this->fields[self::CUSTOMERGROUPID_FIELD] = new IntField('customergroupID');
        $this->fields[self::PARTNERID_FIELD] = new StringField('partnerID');
        $this->fields[self::LANGUAGEID_FIELD] = new IntField('languageID');
        $this->fields[self::ACTIVE_FILTER_FIELD] = new IntField('active_filter');
        $this->fields[self::IMAGE_FILTER_FIELD] = new IntField('image_filter');
        $this->fields[self::STOCKMIN_FILTER_FIELD] = new IntField('stockmin_filter');
        $this->fields[self::INSTOCK_FILTER_FIELD] = (new IntField('instock_filter'))->setFlags(new Required());
        $this->fields[self::PRICE_FILTER_FIELD] = (new FloatField('price_filter'))->setFlags(new Required());
        $this->fields[self::OWN_FILTER_FIELD] = (new LongTextField('own_filter'))->setFlags(new Required());
        $this->fields[self::HEADER_FIELD] = (new LongTextField('header'))->setFlags(new Required());
        $this->fields[self::BODY_FIELD] = (new LongTextField('body'))->setFlags(new Required());
        $this->fields[self::FOOTER_FIELD] = (new LongTextField('footer'))->setFlags(new Required());
        $this->fields[self::COUNT_FILTER_FIELD] = (new IntField('count_filter'))->setFlags(new Required());
        $this->fields[self::MULTISHOPID_FIELD] = new IntField('multishopID');
        $this->fields[self::VARIANT_EXPORT_FIELD] = new IntField('variant_export');
        $this->fields[self::CACHE_REFRESHED_FIELD] = new DateField('cache_refreshed');
        $this->fields[self::DIRTY_FIELD] = new IntField('dirty');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ExportWrittenEvent
    {
        $event = new ExportWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
