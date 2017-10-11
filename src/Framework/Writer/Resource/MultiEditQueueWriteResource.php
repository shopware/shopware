<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\MultiEditQueueWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class MultiEditQueueWriteResource extends WriteResource
{
    protected const RESOURCE_FIELD = 'resource';
    protected const FILTER_STRING_FIELD = 'filterString';
    protected const OPERATIONS_FIELD = 'operations';
    protected const ITEMS_FIELD = 'items';
    protected const ACTIVE_FIELD = 'active';
    protected const CREATED_FIELD = 'created';

    public function __construct()
    {
        parent::__construct('s_multi_edit_queue');

        $this->fields[self::RESOURCE_FIELD] = (new StringField('resource'))->setFlags(new Required());
        $this->fields[self::FILTER_STRING_FIELD] = (new LongTextField('filter_string'))->setFlags(new Required());
        $this->fields[self::OPERATIONS_FIELD] = (new LongTextField('operations'))->setFlags(new Required());
        $this->fields[self::ITEMS_FIELD] = (new IntField('items'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::CREATED_FIELD] = new DateField('created');
        $this->fields['articless'] = new SubresourceField(MultiEditQueueArticlesWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            MultiEditQueueArticlesWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MultiEditQueueWrittenEvent
    {
        $event = new MultiEditQueueWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
