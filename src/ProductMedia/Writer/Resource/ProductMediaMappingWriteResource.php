<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductMedia\Event\ProductMediaMappingWrittenEvent;

class ProductMediaMappingWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_media_mapping');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productMedia'] = new ReferenceField('productMediaUuid', 'uuid', ProductMediaWriteResource::class);
        $this->fields['productMediaUuid'] = (new FkField('product_media_uuid', ProductMediaWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductMediaWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductMediaMappingWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductMediaMappingWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
