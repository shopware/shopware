<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class BearerTokenValidator implements AuthorizationValidatorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AuthorizationValidatorInterface
     */
    private $decorated;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(
        AuthorizationValidatorInterface $decorated,
        Connection $connection,
        Configuration $configuration
    ) {
        $this->decorated = $decorated;
        $this->connection = $connection;
        $this->configuration = $configuration;
    }

    /**A
     * {@inheritdoc}
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
     * @throws \Doctrine\DBAL\DBALException
     */
    private function validateAccessTokenIssuedAt(\DateTimeImmutable $tokenIssuedAt, string $userId): void
    {
        $lastUpdatedPasswordAt = $this->connection->createQueryBuilder()
            ->select(['last_updated_password_at'])
            ->from('user')
            ->where('id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->execute()
            ->fetchColumn();

        if ($lastUpdatedPasswordAt === false) {
            throw OAuthServerException::accessDenied('Access token is invalid');
        }

        if ($lastUpdatedPasswordAt === null) {
            return;
        }

        $lastUpdatedPasswordAt = strtotime($lastUpdatedPasswordAt);

        if ($tokenIssuedAt->getTimestamp() <= $lastUpdatedPasswordAt) {
            throw OAuthServerException::accessDenied('Access token is expired');
        }
    }
}
