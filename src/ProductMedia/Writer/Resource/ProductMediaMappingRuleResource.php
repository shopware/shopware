<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductMediaMappingRuleResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_MEDIA_MAPPING_UUID_FIELD = 'productMediaMappingUuid';
    protected const OPTION_ID_FIELD = 'optionId';

    public function __construct()
    {
        parent::__construct('product_media_mapping_rule');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_MEDIA_MAPPING_UUID_FIELD] = (new StringField('product_media_mapping_uuid'))->setFlags(new Required());
        $this->fields[self::OPTION_ID_FIELD] = (new IntField('option_id'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductMedia\Writer\Resource\ProductMediaMappingRuleResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductMedia\Event\ProductMediaMappingRuleWrittenEvent
    {
        $event = new \Shopware\ProductMedia\Event\ProductMediaMappingRuleWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingRuleResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingRuleResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
