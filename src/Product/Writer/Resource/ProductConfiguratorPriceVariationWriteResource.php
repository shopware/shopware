<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductConfiguratorPriceVariationWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const VARIATION_FIELD = 'variation';
    protected const OPTIONS_FIELD = 'options';
    protected const IS_GROSS_FIELD = 'isGross';

    public function __construct()
    {
        parent::__construct('product_configurator_price_variation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = (new IntField('configurator_set_id'))->setFlags(new Required());
        $this->fields[self::VARIATION_FIELD] = (new FloatField('variation'))->setFlags(new Required());
        $this->fields[self::OPTIONS_FIELD] = new LongTextField('options');
        $this->fields[self::IS_GROSS_FIELD] = new IntField('is_gross');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorPriceVariationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorPriceVariationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorPriceVariationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorPriceVariationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorPriceVariationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
