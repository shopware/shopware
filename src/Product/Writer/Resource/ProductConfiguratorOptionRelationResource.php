<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductConfiguratorOptionRelationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_ID_FIELD = 'productId';
    protected const OPTION_ID_FIELD = 'optionId';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_configurator_option_relation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_ID_FIELD] = (new IntField('product_id'))->setFlags(new Required());
        $this->fields[self::OPTION_ID_FIELD] = (new IntField('option_id'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionRelationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorOptionRelationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorOptionRelationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorOptionRelationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionRelationResource::createWrittenEvent($updates, $context));
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
