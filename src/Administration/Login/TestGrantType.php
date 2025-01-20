<?php

declare(strict_types=1);

namespace Shopware\Administration\Login;

use DateInterval;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\RefreshToken;

class TestGrantType extends AbstractGrant implements GrantTypeInterface
{
    public function __construct(
        AccessTokenRepository $accessTokenRepository,
        private $jwtConfig
    ) {}

    public function getIdentifier()
    {
        return 'test_grant';
    }

    public function respondToAccessTokenRequest(ServerRequestInterface $request, ResponseTypeInterface $responseType, DateInterval $accessTokenTTL)
    {
        $requestBody = $request->getParsedBody();

        $identifier = $requestBody['user_id'];

        $client = new ApiClient('administration', true);

        $token = $this->accessTokenRepository->getNewToken($client, [], $identifier);
        $token->setExpiryDateTime((new \DateTimeImmutable())->add($accessTokenTTL));

//        $pk = new CryptKey(\realpath(__DIR__ . '/../../../config/jwt/private.pem'), null, false);
        $pk = new FakeCryptKey($this->jwtConfig);
        $token->setPrivateKey($pk);

        $refreshToken = new RefreshToken();
        $refreshToken->setIdentifier($identifier);
        $refreshToken->setAccessToken($token);
        $refreshToken->setExpiryDateTime((new \DateTimeImmutable())->add($accessTokenTTL));

        $responseType->setAccessToken($token);
        $responseType->setRefreshToken($refreshToken);

        return $responseType;
    }
}
