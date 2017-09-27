<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductConfiguratorOptionResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const GROUP_ID_FIELD = 'groupId';
    protected const PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD = 'productConfiguratorGroupUuid';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_configurator_option');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::GROUP_ID_FIELD] = new IntField('group_id');
        $this->fields[self::PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD] = (new StringField('product_configurator_group_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionResource::class,
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorOptionWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorOptionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorOptionResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
