<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\Tax;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Tax\Definition\TaxDefinition;

class TaxWrittenEvent extends WrittenEvent
{
    public const NAME = 'tax.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxDefinition::class;
    }
}
