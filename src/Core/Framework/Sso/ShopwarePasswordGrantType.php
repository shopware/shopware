<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\UserService\UserService;

/**
 * @internal
 */
#[Package('framework')]
class ShopwarePasswordGrantType extends PasswordGrant
{
    public function __construct(
        UserRepositoryInterface $userRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserService $userService,
    ) {
        parent::__construct($userRepository, $refreshTokenRepository);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $user = $this->validateUser($request, $client);

        $this->userService->removeExternalToken($user->getIdentifier());

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }
}
