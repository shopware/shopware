<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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
            \Shopware\Framework\Write\Resource\CoreEngineGroupsWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CoreEngineGroupsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreEngineGroupsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreEngineGroupsWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreEngineGroupsWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
