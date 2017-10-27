<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingRefererWrittenEvent;

class EmarketingRefererWriteResource extends WriteResource
{
    protected const USERID_FIELD = 'userID';
    protected const REFERER_FIELD = 'referer';
    protected const DATE_FIELD = 'date';

    public function __construct()
    {
        parent::__construct('s_emarketing_referer');

        $this->fields[self::USERID_FIELD] = (new IntField('userID'))->setFlags(new Required());
        $this->fields[self::REFERER_FIELD] = (new LongTextField('referer'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = (new DateField('date'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingRefererWrittenEvent
    {
        $event = new EmarketingRefererWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
