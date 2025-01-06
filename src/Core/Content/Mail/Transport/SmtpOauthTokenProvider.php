<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Transport;

use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class SmtpOauthTokenProvider
{
    private const GRANT_TYPE = 'client_credentials';
    private const CACHE_KEY = 'email-token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly SystemConfigService $configService,
    ) {
    }

    public function getToken(): string
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $cacheItem) {
            return $this->fetchToken($cacheItem);
        });
    }

    private function fetchToken(ItemInterface $cacheItem): string
    {
        $body = [
            'client_id' => $this->configService->getString('core.mailerSettings.clientId'),
            'client_secret' => $this->configService->getString('core.mailerSettings.clientSecret'),
            'scope' => $this->configService->getString('core.mailerSettings.oauthScope'),
            'grant_type' => self::GRANT_TYPE,
        ];

        $response = $this->httpClient->request('POST', $this->configService->getString('core.mailerSettings.oauthUrl'), [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($body),
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw MailException::oauthError('Failed to fetch oauth token: ' . $response->getContent(false));
        }

        $auth = $response->toArray();

        // cache token for 1 minute less than the expiration time
        $cacheItem->expiresAfter($auth['expires_in'] - 60);

        return $auth['access_token'];
    }
}
