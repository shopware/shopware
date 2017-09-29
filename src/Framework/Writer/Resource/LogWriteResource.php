<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class LogWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TYPE_FIELD = 'type';
    protected const KEY_FIELD = 'key';
    protected const TEXT_FIELD = 'text';
    protected const DATE_FIELD = 'date';
    protected const USER_FIELD = 'user';
    protected const IP_ADDRESS_FIELD = 'ipAddress';
    protected const USER_AGENT_FIELD = 'userAgent';
    protected const VALUE4_FIELD = 'value4';

    public function __construct()
    {
        parent::__construct('log');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::KEY_FIELD] = (new StringField('key'))->setFlags(new Required());
        $this->fields[self::TEXT_FIELD] = (new LongTextField('text'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = (new DateField('date'))->setFlags(new Required());
        $this->fields[self::USER_FIELD] = (new StringField('user'))->setFlags(new Required());
        $this->fields[self::IP_ADDRESS_FIELD] = (new StringField('ip_address'))->setFlags(new Required());
        $this->fields[self::USER_AGENT_FIELD] = (new StringField('user_agent'))->setFlags(new Required());
        $this->fields[self::VALUE4_FIELD] = (new StringField('value4'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\LogWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\LogWrittenEvent
    {
        $event = new \Shopware\Framework\Event\LogWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\LogWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\LogWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
