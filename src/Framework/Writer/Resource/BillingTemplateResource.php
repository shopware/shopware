<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class BillingTemplateResource extends Resource
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
            \Shopware\Framework\Write\Resource\BillingTemplateResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\BillingTemplateWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BillingTemplateWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BillingTemplateResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BillingTemplateResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
