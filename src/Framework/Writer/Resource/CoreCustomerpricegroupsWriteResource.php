<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CoreCustomerpricegroupsWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const NETTO_FIELD = 'netto';
    protected const ACTIVE_FIELD = 'active';

    public function __construct()
    {
        parent::__construct('s_core_customerpricegroups');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::NETTO_FIELD] = (new IntField('netto'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreCustomerpricegroupsWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CoreCustomerpricegroupsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreCustomerpricegroupsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreCustomerpricegroupsWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreCustomerpricegroupsWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
