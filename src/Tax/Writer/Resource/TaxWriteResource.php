<?php declare(strict_types=1);

namespace Shopware\Tax\Writer\Resource;

use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\Tax\Event\TaxWrittenEvent;
use Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource;

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
        $this->fields['products'] = new SubresourceField(ProductWriteResource::class);
        $this->fields['areaRules'] = new SubresourceField(TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            self::class,
            TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): TaxWrittenEvent
    {
        $event = new TaxWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
