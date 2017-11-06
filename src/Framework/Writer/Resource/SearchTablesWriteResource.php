<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\SearchTablesWrittenEvent;

class SearchTablesWriteResource extends WriteResource
{
    protected const TABLE_FIELD = 'table';
    protected const REFERENZ_TABLE_FIELD = 'referenzTable';
    protected const FOREIGN_KEY_FIELD = 'foreignKey';
    protected const WHERE_FIELD = 'where';

    public function __construct()
    {
        parent::__construct('s_search_tables');

        $this->fields[self::TABLE_FIELD] = (new StringField('table'))->setFlags(new Required());
        $this->fields[self::REFERENZ_TABLE_FIELD] = new StringField('referenz_table');
        $this->fields[self::FOREIGN_KEY_FIELD] = new StringField('foreign_key');
        $this->fields[self::WHERE_FIELD] = new StringField('where');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): SearchTablesWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new SearchTablesWrittenEvent($uuids, $context, $rawData, $errors);

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
