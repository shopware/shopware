<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderComparisonsResource extends Resource
{
    protected const SESSIONID_FIELD = 'sessionID';
    protected const USERID_FIELD = 'userID';
    protected const ARTICLENAME_FIELD = 'articlename';
    protected const ARTICLEID_FIELD = 'articleID';
    protected const DATUM_FIELD = 'datum';

    public function __construct()
    {
        parent::__construct('s_order_comparisons');

        $this->fields[self::SESSIONID_FIELD] = new StringField('sessionID');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::ARTICLENAME_FIELD] = (new StringField('articlename'))->setFlags(new Required());
        $this->fields[self::ARTICLEID_FIELD] = new IntField('articleID');
        $this->fields[self::DATUM_FIELD] = new DateField('datum');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderComparisonsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\OrderComparisonsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\OrderComparisonsWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\OrderComparisonsResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\OrderComparisonsResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
