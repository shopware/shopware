<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Client;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('merchant-services')]
class GatewayClient
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ShopIdProvider $shopIdProvider,
    ) {
    }

    public function isGatewayAllowsPush(): bool
    {
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
