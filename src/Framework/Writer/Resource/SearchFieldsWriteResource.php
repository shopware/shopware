<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\SearchFieldsWrittenEvent;

class SearchFieldsWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const RELEVANCE_FIELD = 'relevance';
    protected const FIELD_FIELD = 'field';
    protected const TABLEID_FIELD = 'tableID';

    public function __construct()
    {
        parent::__construct('s_search_fields');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::RELEVANCE_FIELD] = (new IntField('relevance'))->setFlags(new Required());
        $this->fields[self::FIELD_FIELD] = (new StringField('field'))->setFlags(new Required());
        $this->fields[self::TABLEID_FIELD] = (new IntField('tableID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): SearchFieldsWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new SearchFieldsWrittenEvent($uuids, $context, $rawData, $errors);

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
