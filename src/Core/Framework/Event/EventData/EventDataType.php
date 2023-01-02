<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;
/**
 * @package business-ops
 */
#[Package('business-ops')]
interface EventDataType
{
    public function toArray(): array;
}
