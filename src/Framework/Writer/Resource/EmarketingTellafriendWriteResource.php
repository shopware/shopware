<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingTellafriendWrittenEvent;

class EmarketingTellafriendWriteResource extends WriteResource
{
    protected const DATUM_FIELD = 'datum';
    protected const RECIPIENT_FIELD = 'recipient';
    protected const SENDER_FIELD = 'sender';
    protected const CONFIRMED_FIELD = 'confirmed';

    public function __construct()
    {
        parent::__construct('s_emarketing_tellafriend');

        $this->fields[self::DATUM_FIELD] = new DateField('datum');
        $this->fields[self::RECIPIENT_FIELD] = (new StringField('recipient'))->setFlags(new Required());
        $this->fields[self::SENDER_FIELD] = new IntField('sender');
        $this->fields[self::CONFIRMED_FIELD] = new IntField('confirmed');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingTellafriendWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new EmarketingTellafriendWrittenEvent($uuids, $context, $rawData, $errors);

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
