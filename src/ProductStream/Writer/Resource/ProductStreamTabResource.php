<?php declare(strict_types=1);

namespace Shopware\ProductStream\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductStreamTabResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_stream_tab');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['productStream'] = new ReferenceField('productStreamUuid', 'uuid', \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class);
        $this->fields['productStreamUuid'] = (new FkField('product_stream_uuid', \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class,
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductStream\Event\ProductStreamTabWrittenEvent
    {
        $event = new \Shopware\ProductStream\Event\ProductStreamTabWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::createWrittenEvent($updates, $context));
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
