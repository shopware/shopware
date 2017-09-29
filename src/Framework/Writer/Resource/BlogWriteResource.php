<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Category\Writer\Resource\CategoryWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\BlogWrittenEvent;
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
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields['user'] = new ReferenceField('userUuid', 'uuid', UserWriteResource::class);
        $this->fields['userUuid'] = (new FkField('user_uuid', UserWriteResource::class, 'uuid'));
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', CategoryWriteResource::class, 'uuid'));
        $this->fields[self::TITLE_FIELD] = new TranslatedField('title', ShopWriteResource::class, 'uuid');
        $this->fields[self::SHORT_DESCRIPTION_FIELD] = new TranslatedField('shortDescription', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(BlogTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['comments'] = new SubresourceField(BlogCommentWriteResource::class);
        $this->fields['media'] = new SubresourceField(BlogMediaWriteResource::class);
        $this->fields['products'] = new SubresourceField(BlogProductWriteResource::class);
        $this->fields['tags'] = new SubresourceField(BlogTagWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            UserWriteResource::class,
            CategoryWriteResource::class,
            self::class,
            BlogTranslationWriteResource::class,
            BlogCommentWriteResource::class,
            BlogMediaWriteResource::class,
            BlogProductWriteResource::class,
            BlogTagWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): BlogWrittenEvent
    {
        $event = new BlogWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[UserWriteResource::class])) {
            $event->addEvent(UserWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CategoryWriteResource::class])) {
            $event->addEvent(CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[BlogTranslationWriteResource::class])) {
            $event->addEvent(BlogTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[BlogCommentWriteResource::class])) {
            $event->addEvent(BlogCommentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[BlogMediaWriteResource::class])) {
            $event->addEvent(BlogMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[BlogProductWriteResource::class])) {
            $event->addEvent(BlogProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[BlogTagWriteResource::class])) {
            $event->addEvent(BlogTagWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
