<?php declare(strict_types=1);

namespace Shopware\Tax\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class TaxWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'tax.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'tax';
    }
}
