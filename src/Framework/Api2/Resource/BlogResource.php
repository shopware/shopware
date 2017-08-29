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

class BlogResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_blog');
        
        $this->fields['title'] = (new StringField('title'))->setFlags(new Required());
        $this->fields['authorId'] = new IntField('author_id');
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['shortDescription'] = (new LongTextField('short_description'))->setFlags(new Required());
        $this->fields['description'] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields['views'] = new IntField('views');
        $this->fields['displayDate'] = (new DateField('display_date'))->setFlags(new Required());
        $this->fields['categoryId'] = new IntField('category_id');
        $this->fields['template'] = (new StringField('template'))->setFlags(new Required());
        $this->fields['metaKeywords'] = new StringField('meta_keywords');
        $this->fields['metaDescription'] = new StringField('meta_description');
        $this->fields['metaTitle'] = new StringField('meta_title');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\BlogResource::class
        ];
    }
}
