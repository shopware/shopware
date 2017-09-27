<?php declare(strict_types=1);

namespace Shopware\Unit\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class UnitResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHORT_CODE_FIELD = 'shortCode';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('unit');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHORT_CODE_FIELD] = new TranslatedField('shortCode', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Unit\Writer\Resource\UnitTranslationResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Unit\Writer\Resource\UnitResource::class,
            \Shopware\Unit\Writer\Resource\UnitTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Unit\Event\UnitWrittenEvent
    {
        $event = new \Shopware\Unit\Event\UnitWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Unit\Writer\Resource\UnitResource::class])) {
            $event->addEvent(\Shopware\Unit\Writer\Resource\UnitResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Unit\Writer\Resource\UnitTranslationResource::class])) {
            $event->addEvent(\Shopware\Unit\Writer\Resource\UnitTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
