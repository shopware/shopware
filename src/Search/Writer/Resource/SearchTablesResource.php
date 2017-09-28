<?php declare(strict_types=1);

namespace Shopware\Search\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class SearchTablesResource extends Resource
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
            \Shopware\Search\Writer\Resource\SearchTablesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Search\Event\SearchTablesWrittenEvent
    {
        $event = new \Shopware\Search\Event\SearchTablesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Search\Writer\Resource\SearchTablesResource::class])) {
            $event->addEvent(\Shopware\Search\Writer\Resource\SearchTablesResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
