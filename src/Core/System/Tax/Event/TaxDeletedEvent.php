<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Tax\TaxDefinition;

class TaxDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'tax.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxDefinition::class;
    }
}
