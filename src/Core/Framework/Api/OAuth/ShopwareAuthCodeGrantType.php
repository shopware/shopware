<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;

/**
 * Authorization code grant that only accepts the `S256` PKCE code challenge method.
 * The `plain` method is registered unconditionally by the library, but is forbidden by RFC 9700.
 *
 * @internal
 */
#[Package('framework')]
class ShopwareAuthCodeGrantType extends AuthCodeGrant
{
    public const TYPE = 'authorization_code';

    public const CODE_CHALLENGE_METHOD = 'S256';

    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $codeChallengeMethod = $request->getQueryParams()['code_challenge_method'] ?? null;

        if ($codeChallengeMethod !== self::CODE_CHALLENGE_METHOD) {
            throw OAuthServerException::invalidRequest(
                'code_challenge_method',
                'Code challenge method must be `' . self::CODE_CHALLENGE_METHOD . '`.'
            );
        }

        return parent::validateAuthorizationRequest($request);
    }

    protected function validateRedirectUri(string $redirectUri, ClientEntityInterface $client, ServerRequestInterface $request): void
    {
        try {
            parent::validateRedirectUri($redirectUri, $client, $request);
        } catch (OAuthServerException $exception) {
            // The library reports an invalid callback as a generic client authentication failure.
            throw ApiException::invalidOAuthRedirectUri($exception);
        }
    }
}
