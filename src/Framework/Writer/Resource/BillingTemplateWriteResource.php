<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\BillingTemplateWrittenEvent;

class BillingTemplateWriteResource extends WriteResource
{
    protected const ID_FIELD = 'iD';
    protected const NAME_FIELD = 'name';
    protected const VALUE_FIELD = 'value';
    protected const TYP_FIELD = 'typ';
    protected const GROUP_FIELD = 'group';
    protected const DESC_FIELD = 'desc';
    protected const POSITION_FIELD = 'position';
    protected const SHOW_FIELD = 'show';

    public function __construct()
    {
        parent::__construct('s_billing_template');

        $this->primaryKeyFields[self::ID_FIELD] = (new IntField('ID'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields[self::TYP_FIELD] = (new IntField('typ'))->setFlags(new Required());
        $this->fields[self::GROUP_FIELD] = (new StringField('group'))->setFlags(new Required());
        $this->fields[self::DESC_FIELD] = (new StringField('desc'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::SHOW_FIELD] = new IntField('show');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): BillingTemplateWrittenEvent
    {
        $event = new BillingTemplateWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
