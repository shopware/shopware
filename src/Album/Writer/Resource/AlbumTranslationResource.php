<?php declare(strict_types=1);

namespace Shopware\Album\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class AlbumTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('album_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['album'] = new ReferenceField('albumUuid', 'uuid', \Shopware\Album\Writer\Resource\AlbumResource::class);
        $this->primaryKeyFields['albumUuid'] = (new FkField('album_uuid', \Shopware\Album\Writer\Resource\AlbumResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Album\Writer\Resource\AlbumResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Album\Writer\Resource\AlbumTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Album\Event\AlbumTranslationWrittenEvent
    {
        $event = new \Shopware\Album\Event\AlbumTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Album\Writer\Resource\AlbumResource::class])) {
            $event->addEvent(\Shopware\Album\Writer\Resource\AlbumResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Album\Writer\Resource\AlbumTranslationResource::class])) {
            $event->addEvent(\Shopware\Album\Writer\Resource\AlbumTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
