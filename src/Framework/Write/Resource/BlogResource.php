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

class BlogResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const TITLE_FIELD = 'title';
    protected const ACTIVE_FIELD = 'active';
    protected const SHORT_DESCRIPTION_FIELD = 'shortDescription';
    protected const DESCRIPTION_FIELD = 'description';
    protected const VIEWS_FIELD = 'views';
    protected const DISPLAY_DATE_FIELD = 'displayDate';
    protected const TEMPLATE_FIELD = 'template';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const META_TITLE_FIELD = 'metaTitle';

    public function __construct()
    {
        parent::__construct('blog');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = (new StringField('title'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::SHORT_DESCRIPTION_FIELD] = (new LongTextField('short_description'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::VIEWS_FIELD] = new IntField('views');
        $this->fields[self::DISPLAY_DATE_FIELD] = (new DateField('display_date'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::META_KEYWORDS_FIELD] = new StringField('meta_keywords');
        $this->fields[self::META_DESCRIPTION_FIELD] = new StringField('meta_description');
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields['user'] = new ReferenceField('userUuid', 'uuid', \Shopware\Framework\Write\Resource\UserResource::class);
        $this->fields['userUuid'] = (new FkField('user_uuid', \Shopware\Framework\Write\Resource\UserResource::class, 'uuid'));
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class, 'uuid'));
        $this->fields['comments'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogCommentResource::class);
        $this->fields['medias'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogMediaResource::class);
        $this->fields['products'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogProductResource::class);
        $this->fields['tags'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogTagResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\UserResource::class,
            \Shopware\Category\Gateway\Resource\CategoryResource::class,
            \Shopware\Framework\Write\Resource\BlogResource::class,
            \Shopware\Framework\Write\Resource\BlogCommentResource::class,
            \Shopware\Framework\Write\Resource\BlogMediaResource::class,
            \Shopware\Framework\Write\Resource\BlogProductResource::class,
            \Shopware\Framework\Write\Resource\BlogTagResource::class
        ];
    }
}
