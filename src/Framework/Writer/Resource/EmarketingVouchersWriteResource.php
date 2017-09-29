<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingVouchersWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmarketingVouchersWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';
    protected const VOUCHERCODE_FIELD = 'vouchercode';
    protected const NUMBEROFUNITS_FIELD = 'numberofunits';
    protected const VALUE_FIELD = 'value';
    protected const MINIMUMCHARGE_FIELD = 'minimumcharge';
    protected const SHIPPINGFREE_FIELD = 'shippingfree';
    protected const BINDTOSUPPLIER_FIELD = 'bindtosupplier';
    protected const VALID_FROM_FIELD = 'validFrom';
    protected const VALID_TO_FIELD = 'validTo';
    protected const ORDERCODE_FIELD = 'ordercode';
    protected const MODUS_FIELD = 'modus';
    protected const PERCENTAL_FIELD = 'percental';
    protected const NUMORDER_FIELD = 'numorder';
    protected const CUSTOMERGROUP_FIELD = 'customergroup';
    protected const RESTRICTARTICLES_FIELD = 'restrictarticles';
    protected const STRICT_FIELD = 'strict';
    protected const SUBSHOPID_FIELD = 'subshopID';
    protected const TAXCONFIG_FIELD = 'taxconfig';

    public function __construct()
    {
        parent::__construct('s_emarketing_vouchers');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::VOUCHERCODE_FIELD] = (new StringField('vouchercode'))->setFlags(new Required());
        $this->fields[self::NUMBEROFUNITS_FIELD] = new IntField('numberofunits');
        $this->fields[self::VALUE_FIELD] = new FloatField('value');
        $this->fields[self::MINIMUMCHARGE_FIELD] = new FloatField('minimumcharge');
        $this->fields[self::SHIPPINGFREE_FIELD] = new IntField('shippingfree');
        $this->fields[self::BINDTOSUPPLIER_FIELD] = new IntField('bindtosupplier');
        $this->fields[self::VALID_FROM_FIELD] = new DateField('valid_from');
        $this->fields[self::VALID_TO_FIELD] = new DateField('valid_to');
        $this->fields[self::ORDERCODE_FIELD] = (new StringField('ordercode'))->setFlags(new Required());
        $this->fields[self::MODUS_FIELD] = new IntField('modus');
        $this->fields[self::PERCENTAL_FIELD] = (new IntField('percental'))->setFlags(new Required());
        $this->fields[self::NUMORDER_FIELD] = (new IntField('numorder'))->setFlags(new Required());
        $this->fields[self::CUSTOMERGROUP_FIELD] = new IntField('customergroup');
        $this->fields[self::RESTRICTARTICLES_FIELD] = (new LongTextField('restrictarticles'))->setFlags(new Required());
        $this->fields[self::STRICT_FIELD] = (new IntField('strict'))->setFlags(new Required());
        $this->fields[self::SUBSHOPID_FIELD] = new IntField('subshopID');
        $this->fields[self::TAXCONFIG_FIELD] = (new StringField('taxconfig'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): EmarketingVouchersWrittenEvent
    {
        $event = new EmarketingVouchersWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
