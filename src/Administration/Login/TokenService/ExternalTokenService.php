<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final class ExternalTokenService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoginConfig $loginConfigService,
    ) {
    }

    public function getUserToken(string $code): TokenResult
    {
        $tokenResponse = $this->client->request('POST', $this->loginConfigService->getBaseUrl() . '/oauth/access_token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'scope' => 'openid',
                'client_id' => $this->loginConfigService->getClientId(),
                'client_secret' => $this->loginConfigService->getClientSecret(),
                'code' => $code,
                'redirect_uri' => $this->loginConfigService->getRedirectUri(),
            ],
        ]);

        return TokenResult::createFromResponse($tokenResponse->getContent());
    }
}
