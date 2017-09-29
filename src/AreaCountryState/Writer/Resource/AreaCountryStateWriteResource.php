<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Writer\Resource;

use Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource;
use Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource;

class AreaCountryStateWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHORT_CODE_FIELD = 'shortCode';
    protected const POSITION_FIELD = 'position';
    protected const ACTIVE_FIELD = 'active';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area_country_state');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHORT_CODE_FIELD] = (new StringField('short_code'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', AreaCountryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(AreaCountryStateTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['customerAddresses'] = new SubresourceField(CustomerAddressWriteResource::class);
        $this->fields['orderAddresses'] = new SubresourceField(OrderAddressWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            AreaCountryWriteResource::class,
            self::class,
            AreaCountryStateTranslationWriteResource::class,
            CustomerAddressWriteResource::class,
            OrderAddressWriteResource::class,
            TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): AreaCountryStateWrittenEvent
    {
        $event = new AreaCountryStateWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[AreaCountryWriteResource::class])) {
            $event->addEvent(AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[AreaCountryStateTranslationWriteResource::class])) {
            $event->addEvent(AreaCountryStateTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerAddressWriteResource::class])) {
            $event->addEvent(CustomerAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderAddressWriteResource::class])) {
            $event->addEvent(OrderAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[TaxAreaRuleWriteResource::class])) {
            $event->addEvent(TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
