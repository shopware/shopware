<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\MultiEditQueueWrittenEvent;

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
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
