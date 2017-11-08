<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CrontabWrittenEvent;

class CrontabWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const ACTION_FIELD = 'action';
    protected const ELEMENTID_FIELD = 'elementID';
    protected const DATA_FIELD = 'data';
    protected const NEXT_FIELD = 'next';
    protected const START_FIELD = 'start';
    protected const INTERVAL_FIELD = 'interval';
    protected const ACTIVE_FIELD = 'active';
    protected const DISABLE_ON_ERROR_FIELD = 'disableOnError';
    protected const END_FIELD = 'end';
    protected const INFORM_TEMPLATE_FIELD = 'informTemplate';
    protected const INFORM_MAIL_FIELD = 'informMail';
    protected const PLUGINID_FIELD = 'pluginID';

    public function __construct()
    {
        parent::__construct('s_crontab');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::ACTION_FIELD] = (new StringField('action'))->setFlags(new Required());
        $this->fields[self::ELEMENTID_FIELD] = new IntField('elementID');
        $this->fields[self::DATA_FIELD] = (new LongTextField('data'))->setFlags(new Required());
        $this->fields[self::NEXT_FIELD] = new DateField('next');
        $this->fields[self::START_FIELD] = new DateField('start');
        $this->fields[self::INTERVAL_FIELD] = (new IntField('interval'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::DISABLE_ON_ERROR_FIELD] = new BoolField('disable_on_error');
        $this->fields[self::END_FIELD] = new DateField('end');
        $this->fields[self::INFORM_TEMPLATE_FIELD] = (new StringField('inform_template'))->setFlags(new Required());
        $this->fields[self::INFORM_MAIL_FIELD] = (new StringField('inform_mail'))->setFlags(new Required());
        $this->fields[self::PLUGINID_FIELD] = new IntField('pluginID');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CrontabWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CrontabWrittenEvent($uuids, $context, $rawData, $errors);

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
