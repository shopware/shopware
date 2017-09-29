<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreMenuWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): CoreMenuWrittenEvent
    {
        $event = new CoreMenuWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
