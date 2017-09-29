<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class BlogTranslationWriteResource extends WriteResource
{
    protected const TITLE_FIELD = 'title';
    protected const SHORT_DESCRIPTION_FIELD = 'shortDescription';
    protected const DESCRIPTION_FIELD = 'description';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const META_TITLE_FIELD = 'metaTitle';

    public function __construct()
    {
        parent::__construct('blog_translation');

        $this->fields[self::TITLE_FIELD] = (new StringField('title'))->setFlags(new Required());
        $this->fields[self::SHORT_DESCRIPTION_FIELD] = (new LongTextField('short_description'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::META_KEYWORDS_FIELD] = new StringField('meta_keywords');
        $this->fields[self::META_DESCRIPTION_FIELD] = new StringField('meta_description');
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', \Shopware\Framework\Write\Resource\BlogWriteResource::class);
        $this->primaryKeyFields['blogUuid'] = (new FkField('blog_uuid', \Shopware\Framework\Write\Resource\BlogWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\BlogTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BlogTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
