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

class EmotionResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_emotion');
        
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['cols'] = new IntField('cols');
        $this->fields['cellSpacing'] = (new IntField('cell_spacing'))->setFlags(new Required());
        $this->fields['cellHeight'] = (new IntField('cell_height'))->setFlags(new Required());
        $this->fields['articleHeight'] = (new IntField('article_height'))->setFlags(new Required());
        $this->fields['rows'] = (new IntField('rows'))->setFlags(new Required());
        $this->fields['validFrom'] = new DateField('valid_from');
        $this->fields['validTo'] = new DateField('valid_to');
        $this->fields['userID'] = new IntField('userID');
        $this->fields['showListing'] = (new IntField('show_listing'))->setFlags(new Required());
        $this->fields['isLandingpage'] = (new IntField('is_landingpage'))->setFlags(new Required());
        $this->fields['seoTitle'] = (new StringField('seo_title'))->setFlags(new Required());
        $this->fields['seoKeywords'] = (new StringField('seo_keywords'))->setFlags(new Required());
        $this->fields['seoDescription'] = (new LongTextField('seo_description'))->setFlags(new Required());
        $this->fields['createDate'] = new DateField('create_date');
        $this->fields['modified'] = new DateField('modified');
        $this->fields['templateId'] = new IntField('template_id');
        $this->fields['device'] = new StringField('device');
        $this->fields['fullscreen'] = new IntField('fullscreen');
        $this->fields['mode'] = new StringField('mode');
        $this->fields['position'] = new IntField('position');
        $this->fields['parentId'] = new IntField('parent_id');
        $this->fields['previewId'] = new IntField('preview_id');
        $this->fields['previewSecret'] = new StringField('preview_secret');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\EmotionResource::class
        ];
    }
}
