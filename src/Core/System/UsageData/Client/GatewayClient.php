<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Client;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('data-services')]
class GatewayClient
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly bool $dispatchEnabled,
    ) {
    }

    public function isGatewayAllowsPush(): bool
    {
        if (!$this->dispatchEnabled) {
            return false;
        }

        $response = $this->client->request(
            Request::METHOD_GET,
            '/killswitch',
            [
                'headers' => [
                    'Shopware-Shop-Id' => $this->shopIdProvider->getShopId(),
                ],
            ]
        );

        $body = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return !$body['killswitch'];
    }
}
