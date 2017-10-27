<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class ExportCategoriesWrittenEvent extends WrittenEvent
{
    const NAME = 's_export_categories.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_export_categories';
    }
}
