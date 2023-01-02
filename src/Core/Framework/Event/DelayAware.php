<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
/**
 * @internal
 *
 * @deprecated tag:v6.5.0 - will be removed
 * @package business-ops
 */
#[Package('business-ops')]
interface DelayAware extends FlowEventAware
{
}
