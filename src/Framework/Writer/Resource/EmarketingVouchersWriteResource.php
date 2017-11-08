<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingVouchersWrittenEvent;

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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingVouchersWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new EmarketingVouchersWrittenEvent($uuids, $context, $rawData, $errors);

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
