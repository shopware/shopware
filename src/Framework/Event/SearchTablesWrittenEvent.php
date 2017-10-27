<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class SearchTablesWrittenEvent extends WrittenEvent
{
    const NAME = 's_search_tables.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_search_tables';
    }
}
