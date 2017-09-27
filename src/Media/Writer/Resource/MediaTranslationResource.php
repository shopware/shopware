<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MediaTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('media_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaResource::class);
        $this->primaryKeyFields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Media\Writer\Resource\MediaResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Media\Writer\Resource\MediaTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Media\Event\MediaTranslationWrittenEvent
    {
        $event = new \Shopware\Media\Event\MediaTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaTranslationResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
