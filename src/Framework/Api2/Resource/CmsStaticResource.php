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

class CmsStaticResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_cms_static');
        
        $this->fields['tpl1variable'] = (new StringField('tpl1variable'))->setFlags(new Required());
        $this->fields['tpl1path'] = (new StringField('tpl1path'))->setFlags(new Required());
        $this->fields['tpl2variable'] = (new StringField('tpl2variable'))->setFlags(new Required());
        $this->fields['tpl2path'] = (new StringField('tpl2path'))->setFlags(new Required());
        $this->fields['tpl3variable'] = (new StringField('tpl3variable'))->setFlags(new Required());
        $this->fields['tpl3path'] = (new StringField('tpl3path'))->setFlags(new Required());
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['html'] = (new LongTextField('html'))->setFlags(new Required());
        $this->fields['grouping'] = (new StringField('grouping'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['link'] = (new StringField('link'))->setFlags(new Required());
        $this->fields['target'] = (new StringField('target'))->setFlags(new Required());
        $this->fields['parentID'] = new IntField('parentID');
        $this->fields['pageTitle'] = (new StringField('page_title'))->setFlags(new Required());
        $this->fields['metaKeywords'] = (new StringField('meta_keywords'))->setFlags(new Required());
        $this->fields['metaDescription'] = (new StringField('meta_description'))->setFlags(new Required());
        $this->fields['changed'] = new DateField('changed');
        $this->fields['shopIds'] = new StringField('shop_ids');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CmsStaticResource::class
        ];
    }
}
