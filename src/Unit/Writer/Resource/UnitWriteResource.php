<?php declare(strict_types=1);

namespace Shopware\Unit\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\Unit\Event\UnitWrittenEvent;

class UnitWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHORT_CODE_FIELD = 'shortCode';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('unit');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHORT_CODE_FIELD] = new TranslatedField('shortCode', ShopWriteResource::class, 'uuid');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(UnitTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            UnitTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): UnitWrittenEvent
    {
        $event = new UnitWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[UnitTranslationWriteResource::class])) {
            $event->addEvent(UnitTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
