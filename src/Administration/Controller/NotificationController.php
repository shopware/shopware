<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use Shopware\Core\Framework\Notification\Api\NotificationController instead
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class NotificationController extends \Shopware\Core\Framework\Notification\Api\NotificationController
{
}
