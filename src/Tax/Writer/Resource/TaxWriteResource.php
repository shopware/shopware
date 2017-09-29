<?php declare(strict_types=1);

namespace Shopware\Tax\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class TaxWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const RATE_FIELD = 'rate';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('tax');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductWriteResource::class);
        $this->fields['areaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\Tax\Writer\Resource\TaxWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Tax\Event\TaxWrittenEvent
    {
        $event = new \Shopware\Tax\Event\TaxWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Tax\Writer\Resource\TaxWriteResource::class])) {
            $event->addEvent(\Shopware\Tax\Writer\Resource\TaxWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
