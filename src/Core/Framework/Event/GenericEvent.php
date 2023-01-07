<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * @package business-ops
 */
interface GenericEvent
{
    public function getName(): string;
}
