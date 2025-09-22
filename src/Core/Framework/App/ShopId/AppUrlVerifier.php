<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerifier
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    public function verify(ShopId $shopId): void
    {
        $appUrl = $shopId->getFingerprint(AppUrl::IDENTIFIER);

        if ($appUrl === null) {
            // we leave the result as pending
            // verification will be triggered when the url is updated
            return;
        }

        $runId = bin2hex(random_bytes(8));
        $cacheKey = "app_url_check-$runId";

        $this->cache->delete($cacheKey);
        $token = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(60 * 2); // 2 minutes

            return bin2hex(random_bytes(16));
        });

        $url = rtrim($appUrl, '/') . '/api/app-system/shop/verify';

        try {
            $resp = $this->httpClient->request('GET', $url, [
                'max_redirects' => 3,
                'timeout' => 2.0,
                'query' => [
                    'rid' => $runId,
                    'token' => $token,
                    'rand' => random_int(1, \PHP_INT_MAX),
                ],
                'headers' => [
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
            ]);

            $this->persistVerificationResult(
                $shopId,
                $resp->getStatusCode() === 200
            );
        } catch (\Throwable $e) {
            $this->cache->delete($cacheKey);

            $this->persistVerificationResult($shopId, false);
        }
    }

    private function persistVerificationResult(ShopId $shopId, bool $result): void
    {
        $this->shopIdProvider->updateShopId(
            $shopId->withUrlVerificationResult($result)
        );
    }
}
