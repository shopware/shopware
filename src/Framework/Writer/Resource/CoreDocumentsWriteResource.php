<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreDocumentsWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CoreDocumentsWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreDocumentsWrittenEvent
    {
        $event = new CoreDocumentsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
