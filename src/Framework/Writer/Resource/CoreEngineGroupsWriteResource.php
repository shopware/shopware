<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreEngineGroupsWrittenEvent;

class CoreEngineGroupsWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const LABEL_FIELD = 'label';
    protected const LAYOUT_FIELD = 'layout';
    protected const VARIANTABLE_FIELD = 'variantable';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_core_engine_groups');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = new StringField('label');
        $this->fields[self::LAYOUT_FIELD] = new StringField('layout');
        $this->fields[self::VARIANTABLE_FIELD] = new IntField('variantable');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreEngineGroupsWrittenEvent
    {
        $event = new CoreEngineGroupsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
