<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingVoucherCodesWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmarketingVoucherCodesWriteResource extends WriteResource
{
    protected const VOUCHERID_FIELD = 'voucherID';
    protected const USERID_FIELD = 'userID';
    protected const CODE_FIELD = 'code';
    protected const CASHED_FIELD = 'cashed';

    public function __construct()
    {
        parent::__construct('s_emarketing_voucher_codes');

        $this->fields[self::VOUCHERID_FIELD] = new IntField('voucherID');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::CODE_FIELD] = (new StringField('code'))->setFlags(new Required());
        $this->fields[self::CASHED_FIELD] = (new IntField('cashed'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingVoucherCodesWrittenEvent
    {
        $event = new EmarketingVoucherCodesWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
