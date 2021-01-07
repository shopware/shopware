<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Parser;
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

    public function __construct(
        AuthorizationValidatorInterface $decorated,
        Connection $connection
    ) {
        $this->decorated = $decorated;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthorization(ServerRequestInterface $request)
    {
        $request = $this->decorated->validateAuthorization($request);

        $header = $request->getHeader('authorization');

        $jwt = trim(preg_replace('/^(?:\s+)?Bearer\s/', '', $header[0]) ?? '');

        $token = (new Parser())->parse($jwt);

        if ($userId = $request->getAttribute(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID)) {
            $this->validateAccessTokenIssuedAt($token->getClaim('iat', 0), $userId);
        }

        return $request;
    }

    /**
     * @throws OAuthServerException
     * @throws \Doctrine\DBAL\DBALException
     */
    private function validateAccessTokenIssuedAt(int $tokenIssuedAt, string $userId): void
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

        if ($tokenIssuedAt <= $lastUpdatedPasswordAt) {
            throw OAuthServerException::accessDenied('Access token is expired');
        }
    }
}
