<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 */
#[Package('core')]
class BeforeSendRedirectResponseEvent extends BeforeSendResponseEvent
{
}
