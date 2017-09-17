<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderDocumentsResource extends Resource
{
    protected const ID_FIELD = 'iD';
    protected const DATE_FIELD = 'date';
    protected const TYPE_FIELD = 'type';
    protected const USERID_FIELD = 'userID';
    protected const ORDERID_FIELD = 'orderID';
    protected const AMOUNT_FIELD = 'amount';
    protected const DOCID_FIELD = 'docID';
    protected const HASH_FIELD = 'hash';

    public function __construct()
    {
        parent::__construct('s_order_documents');

        $this->primaryKeyFields[self::ID_FIELD] = (new IntField('ID'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = (new DateField('date'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new IntField('type'))->setFlags(new Required());
        $this->fields[self::USERID_FIELD] = (new IntField('userID'))->setFlags(new Required());
        $this->fields[self::ORDERID_FIELD] = (new IntField('orderID'))->setFlags(new Required());
        $this->fields[self::AMOUNT_FIELD] = (new FloatField('amount'))->setFlags(new Required());
        $this->fields[self::DOCID_FIELD] = (new IntField('docID'))->setFlags(new Required());
        $this->fields[self::HASH_FIELD] = (new StringField('hash'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderDocumentsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\OrderDocumentsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\OrderDocumentsWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\OrderDocumentsResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\OrderDocumentsResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
