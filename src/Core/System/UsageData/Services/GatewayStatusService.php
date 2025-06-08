<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Client\GatewayClient;
use Symfony\Component\HttpClient\Exception\ServerException;

/**
 * @internal
 */
#[Package('data-services')]
class GatewayStatusService
{
    public function __construct(
        private readonly GatewayClient $gatewayClient,
    ) {
    }

    public function isGatewayAllowsPush(): bool
    {
        try {
            return $this->gatewayClient->isGatewayAllowsPush();
        } catch (ServerException) {
            return false;
        }
    }
}
