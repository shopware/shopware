<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\SearchIndexWrittenEvent;

class SearchIndexWriteResource extends WriteResource
{
    protected const KEYWORDID_FIELD = 'keywordID';
    protected const FIELDID_FIELD = 'fieldID';
    protected const ELEMENTID_FIELD = 'elementID';

    public function __construct()
    {
        parent::__construct('s_search_index');

        $this->primaryKeyFields[self::KEYWORDID_FIELD] = (new IntField('keywordID'))->setFlags(new Required());
        $this->primaryKeyFields[self::FIELDID_FIELD] = (new IntField('fieldID'))->setFlags(new Required());
        $this->primaryKeyFields[self::ELEMENTID_FIELD] = (new IntField('elementID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): SearchIndexWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new SearchIndexWrittenEvent($uuids, $context, $rawData, $errors);

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
