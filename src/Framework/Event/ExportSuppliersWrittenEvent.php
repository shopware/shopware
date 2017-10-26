<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ExportSuppliersWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_export_suppliers.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_export_suppliers';
    }
}
