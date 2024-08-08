<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\InAppPurchases\Event;

use Shopware\Core\Framework\App\InAppPurchases\Gateway\InAppPurchasesGateway;
use Shopware\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched once a response is received from the app server after making a call to the
 * InAppPurchasesGateway.
 *
 * @internal
 *
 * @see InAppPurchasesGateway::process() for an example implementation
 */
#[Package('checkout')]
class InAppPurchasesGatewayEvent extends Event
{
    public function __construct(
        private readonly InAppPurchasesResponse $response,
    ) {
    }

    public function getResponse(): InAppPurchasesResponse
    {
        return $this->response;
    }
}
