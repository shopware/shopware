<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductAccessoryResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_accessory');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['relatedProduct'] = new ReferenceField('relatedProductUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['relatedProductUuid'] = (new FkField('related_product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Product\Writer\Resource\ProductAccessoryResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductAccessoryWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductAccessoryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAccessoryResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAccessoryResource::createWrittenEvent($updates, $context));
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
