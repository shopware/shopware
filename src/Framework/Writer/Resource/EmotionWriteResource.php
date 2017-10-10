<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmotionWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmotionWriteResource extends WriteResource
{
    protected const ACTIVE_FIELD = 'active';
    protected const NAME_FIELD = 'name';
    protected const COLS_FIELD = 'cols';
    protected const CELL_SPACING_FIELD = 'cellSpacing';
    protected const CELL_HEIGHT_FIELD = 'cellHeight';
    protected const ARTICLE_HEIGHT_FIELD = 'articleHeight';
    protected const ROWS_FIELD = 'rows';
    protected const VALID_FROM_FIELD = 'validFrom';
    protected const VALID_TO_FIELD = 'validTo';
    protected const USERID_FIELD = 'userID';
    protected const SHOW_LISTING_FIELD = 'showListing';
    protected const IS_LANDINGPAGE_FIELD = 'isLandingpage';
    protected const SEO_TITLE_FIELD = 'seoTitle';
    protected const SEO_KEYWORDS_FIELD = 'seoKeywords';
    protected const SEO_DESCRIPTION_FIELD = 'seoDescription';
    protected const CREATE_DATE_FIELD = 'createDate';
    protected const MODIFIED_FIELD = 'modified';
    protected const TEMPLATE_ID_FIELD = 'templateId';
    protected const DEVICE_FIELD = 'device';
    protected const FULLSCREEN_FIELD = 'fullscreen';
    protected const MODE_FIELD = 'mode';
    protected const POSITION_FIELD = 'position';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const PREVIEW_ID_FIELD = 'previewId';
    protected const PREVIEW_SECRET_FIELD = 'previewSecret';

    public function __construct()
    {
        parent::__construct('s_emotion');

        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::COLS_FIELD] = new IntField('cols');
        $this->fields[self::CELL_SPACING_FIELD] = (new IntField('cell_spacing'))->setFlags(new Required());
        $this->fields[self::CELL_HEIGHT_FIELD] = (new IntField('cell_height'))->setFlags(new Required());
        $this->fields[self::ARTICLE_HEIGHT_FIELD] = (new IntField('article_height'))->setFlags(new Required());
        $this->fields[self::ROWS_FIELD] = (new IntField('rows'))->setFlags(new Required());
        $this->fields[self::VALID_FROM_FIELD] = new DateField('valid_from');
        $this->fields[self::VALID_TO_FIELD] = new DateField('valid_to');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::SHOW_LISTING_FIELD] = (new IntField('show_listing'))->setFlags(new Required());
        $this->fields[self::IS_LANDINGPAGE_FIELD] = (new IntField('is_landingpage'))->setFlags(new Required());
        $this->fields[self::SEO_TITLE_FIELD] = (new StringField('seo_title'))->setFlags(new Required());
        $this->fields[self::SEO_KEYWORDS_FIELD] = (new StringField('seo_keywords'))->setFlags(new Required());
        $this->fields[self::SEO_DESCRIPTION_FIELD] = (new LongTextField('seo_description'))->setFlags(new Required());
        $this->fields[self::CREATE_DATE_FIELD] = new DateField('create_date');
        $this->fields[self::MODIFIED_FIELD] = new DateField('modified');
        $this->fields[self::TEMPLATE_ID_FIELD] = new IntField('template_id');
        $this->fields[self::DEVICE_FIELD] = new StringField('device');
        $this->fields[self::FULLSCREEN_FIELD] = new IntField('fullscreen');
        $this->fields[self::MODE_FIELD] = new StringField('mode');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::PREVIEW_ID_FIELD] = new IntField('preview_id');
        $this->fields[self::PREVIEW_SECRET_FIELD] = new StringField('preview_secret');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmotionWrittenEvent
    {
        $event = new EmotionWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
