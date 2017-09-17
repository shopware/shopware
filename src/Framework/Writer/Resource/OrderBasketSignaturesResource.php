<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderBasketSignaturesResource extends Resource
{
    protected const SIGNATURE_FIELD = 'signature';
    protected const BASKET_FIELD = 'basket';
    protected const CREATED_AT_FIELD = 'createdAt';

    public function __construct()
    {
        parent::__construct('s_order_basket_signatures');

        $this->primaryKeyFields[self::SIGNATURE_FIELD] = (new StringField('signature'))->setFlags(new Required());
        $this->fields[self::BASKET_FIELD] = (new LongTextField('basket'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderBasketSignaturesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\OrderBasketSignaturesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\OrderBasketSignaturesWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\OrderBasketSignaturesResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\OrderBasketSignaturesResource::createWrittenEvent($updates));
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
