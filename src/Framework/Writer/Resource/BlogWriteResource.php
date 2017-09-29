<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class BlogWriteResource extends WriteResource
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
        $this->fields['user'] = new ReferenceField('userUuid', 'uuid', \Shopware\Framework\Write\Resource\UserWriteResource::class);
        $this->fields['userUuid'] = (new FkField('user_uuid', \Shopware\Framework\Write\Resource\UserWriteResource::class, 'uuid'));
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class, 'uuid'));
        $this->fields[self::TITLE_FIELD] = new TranslatedField('title', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::SHORT_DESCRIPTION_FIELD] = new TranslatedField('shortDescription', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\BlogTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['comments'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogCommentWriteResource::class);
        $this->fields['media'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogMediaWriteResource::class);
        $this->fields['products'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogProductWriteResource::class);
        $this->fields['tags'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogTagWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\UserWriteResource::class,
            \Shopware\Category\Writer\Resource\CategoryWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogTranslationWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogCommentWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogMediaWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogProductWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogTagWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\BlogWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BlogWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\UserWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\UserWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogCommentWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogCommentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogMediaWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogTagWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogTagWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
