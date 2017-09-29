<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductConfiguratorGroupWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('product_configurator_group');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorGroupWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorGroupWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
