<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('core')]
readonly class SymfonyBearerTokenValidator
{
    /**
     * @internal
     */
    public function __construct(
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private Connection $connection,
        private Configuration $jwtConfiguration
    ) {
    }

    public function validateAuthorization(Request $request): void
    {
        if ($request->headers->has('authorization') === false) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }

        $header = $request->headers->get('authorization', '');
        $jwt = \trim((string) \preg_replace('/^\s*Bearer\s/', '', $header));

        if ($jwt === '') {
            throw OAuthServerException::accessDenied('Missing token in "Authorization" header');
        }

        try {
            // Attempt to parse the JWT
            /** @var UnencryptedToken $token */
            $token = $this->jwtConfiguration->parser()->parse($jwt);
        } catch (Exception $exception) {
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);
        }

        try {
            // Attempt to validate the JWT
            $constraints = $this->jwtConfiguration->validationConstraints();
            $this->jwtConfiguration->validator()->assert($token, ...$constraints);
        } catch (RequiredConstraintsViolated $exception) {
            throw OAuthServerException::accessDenied('Access token could not be verified', null, $exception);
        }

        $claims = $token->claims();

        // Check if the token has been revoked
        if ($this->accessTokenRepository->isAccessTokenRevoked($claims->get('jti'))) {
            throw OAuthServerException::accessDenied('Access token has been revoked');
        }

        $request->attributes->set('oauth_access_token_id', $claims->get('jti'));
        $aud = $claims->get('aud');

        if (\is_array($aud)) {
            $aud = array_shift($aud);
        }

        $request->attributes->set('oauth_client_id', $aud);
        $request->attributes->set('oauth_user_id', $claims->get('sub'));
        $request->attributes->set('oauth_scopes', $claims->get('scopes'));

        if ($userId = $claims->get('sub')) {
            $this->validateAccessTokenIssuedAt($token->claims()->get('iat', 0), $userId);
        }
    }

    private function validateAccessTokenIssuedAt(\DateTimeImmutable $tokenIssuedAt, string $userId): void
    {
        $lastUpdatedPasswordAt = $this->connection->createQueryBuilder()
            ->select('last_updated_password_at')
            ->from('user')
            ->where('id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchOne();

        if ($lastUpdatedPasswordAt === false) {
            throw OAuthServerException::accessDenied('Access token is invalid');
        }

        if ($lastUpdatedPasswordAt === null) {
            return;
        }

        $lastUpdated = new \DateTime($lastUpdatedPasswordAt);

        if ($lastUpdated > $tokenIssuedAt) {
            throw OAuthServerException::accessDenied('Access token is expired');
        }
    }
}
