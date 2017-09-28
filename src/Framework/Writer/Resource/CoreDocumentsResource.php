<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CoreDocumentsResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const TEMPLATE_FIELD = 'template';
    protected const NUMBERS_FIELD = 'numbers';
    protected const LEFT_FIELD = 'left';
    protected const RIGHT_FIELD = 'right';
    protected const TOP_FIELD = 'top';
    protected const BOTTOM_FIELD = 'bottom';
    protected const PAGEBREAK_FIELD = 'pagebreak';

    public function __construct()
    {
        parent::__construct('s_core_documents');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::NUMBERS_FIELD] = (new StringField('numbers'))->setFlags(new Required());
        $this->fields[self::LEFT_FIELD] = (new IntField('left'))->setFlags(new Required());
        $this->fields[self::RIGHT_FIELD] = (new IntField('right'))->setFlags(new Required());
        $this->fields[self::TOP_FIELD] = (new IntField('top'))->setFlags(new Required());
        $this->fields[self::BOTTOM_FIELD] = (new IntField('bottom'))->setFlags(new Required());
        $this->fields[self::PAGEBREAK_FIELD] = (new IntField('pagebreak'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreDocumentsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CoreDocumentsWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CoreDocumentsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CoreDocumentsResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
