<?php declare(strict_types=1);

namespace Shopware\Tax\Event\Tax;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Tax\Definition\TaxDefinition;

class TaxWrittenEvent extends WrittenEvent
{
    const NAME = 'tax.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxDefinition::class;
    }
}
