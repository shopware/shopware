<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Category\Writer\Resource\CategoryWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\BlogWrittenEvent;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): BlogWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new BlogWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
