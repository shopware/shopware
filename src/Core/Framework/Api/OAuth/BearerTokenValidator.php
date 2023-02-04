<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

#[Package('core')]
class BearerTokenValidator implements AuthorizationValidatorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AuthorizationValidatorInterface $decorated,
        private readonly Connection $connection,
        private readonly Configuration $configuration
    ) {
    }

    /**
     * @return ServerRequestInterface
     */
    public function validateAuthorization(ServerRequestInterface $request)
    {
        $request = $this->decorated->validateAuthorization($request);

        $header = $request->getHeader('authorization');

        $jwt = trim(preg_replace('/^(?:\s+)?Bearer\s/', '', $header[0]) ?? '');

        /** @var UnencryptedToken $token */
        $token = $this->configuration->parser()->parse($jwt);

        if ($userId = $request->getAttribute(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID)) {
            $this->validateAccessTokenIssuedAt($token->claims()->get('iat', 0), $userId);
        }

        return $request;
    }

    /**
     * @throws OAuthServerException
     */
    private function validateAccessTokenIssuedAt(\DateTimeImmutable $tokenIssuedAt, string $userId): void
    {
        $lastUpdatedPasswordAt = $this->connection->createQueryBuilder()
            ->select(['last_updated_password_at'])
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

        $lastUpdatedPasswordAt = strtotime((string) $lastUpdatedPasswordAt);

        if ($tokenIssuedAt->getTimestamp() <= $lastUpdatedPasswordAt) {
            throw OAuthServerException::accessDenied('Access token is expired');
        }
    }
}
