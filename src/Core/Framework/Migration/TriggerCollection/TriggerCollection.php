<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\TriggerCollection;

use Shopware\Core\Framework\Migration\Trigger;

interface TriggerCollection
{
    /**
     * @return Trigger[]
     */
    public function getTrigger(): array;
}
