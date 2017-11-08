<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreMenuWrittenEvent;

class CoreMenuWriteResource extends WriteResource
{
    protected const PARENT_FIELD = 'parent';
    protected const NAME_FIELD = 'name';
    protected const ONCLICK_FIELD = 'onclick';
    protected const CLASS_FIELD = 'class';
    protected const POSITION_FIELD = 'position';
    protected const ACTIVE_FIELD = 'active';
    protected const PLUGINID_FIELD = 'pluginID';
    protected const CONTROLLER_FIELD = 'controller';
    protected const SHORTCUT_FIELD = 'shortcut';
    protected const ACTION_FIELD = 'action';

    public function __construct()
    {
        parent::__construct('s_core_menu');

        $this->fields[self::PARENT_FIELD] = new IntField('parent');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::ONCLICK_FIELD] = new StringField('onclick');
        $this->fields[self::CLASS_FIELD] = new StringField('class');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::PLUGINID_FIELD] = new IntField('pluginID');
        $this->fields[self::CONTROLLER_FIELD] = new StringField('controller');
        $this->fields[self::SHORTCUT_FIELD] = new StringField('shortcut');
        $this->fields[self::ACTION_FIELD] = new StringField('action');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreMenuWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CoreMenuWrittenEvent($uuids, $context, $rawData, $errors);

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
