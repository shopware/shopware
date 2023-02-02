<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class PaymentScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === PaymentController::class;
    }
}
