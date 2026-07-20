<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class MaintenanceRedirectEvent extends StorefrontRedirectEvent
{
}
