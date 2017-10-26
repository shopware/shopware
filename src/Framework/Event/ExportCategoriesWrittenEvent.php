<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ExportCategoriesWrittenEvent extends AbstractWrittenEvent
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
