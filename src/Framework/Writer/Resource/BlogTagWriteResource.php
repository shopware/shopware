<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class BlogTagWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('blog_tag');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', \Shopware\Framework\Write\Resource\BlogWriteResource::class);
        $this->fields['blogUuid'] = (new FkField('blog_uuid', \Shopware\Framework\Write\Resource\BlogWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\BlogTagTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogTagWriteResource::class,
            \Shopware\Framework\Write\Resource\BlogTagTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\BlogTagWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BlogTagWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogTagWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogTagWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogTagTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogTagTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
