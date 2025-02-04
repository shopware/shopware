<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\Exception\LoginException;
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
        private readonly LoginConfigService  $loginConfigService,
    )
    {
    }

    public function getUserToken(string $code): TokenResult
    {
        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw LoginException::configurationNotFound();
        }

        $tokenResponse = $this->client->request('POST', $loginConfig->baseUrl . '/oauth/access_token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'scope' => 'openid',
                'client_id' => $loginConfig->clientId,
                'client_secret' => $loginConfig->clientSecret,
                'code' => $code,
                'redirect_uri' => $loginConfig->redirectUri,
            ],
        ]);

        return TokenResult::createFromResponse($tokenResponse->getContent());
    }
}
