<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class PriceGroupResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('price_group');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['discounts'] = new SubresourceField(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class,
            \Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class,
            \Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\PriceGroup\Event\PriceGroupWrittenEvent
    {
        $event = new \Shopware\PriceGroup\Event\PriceGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class])) {
            $event->addEvent(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::createWrittenEvent($updates, $context));
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
