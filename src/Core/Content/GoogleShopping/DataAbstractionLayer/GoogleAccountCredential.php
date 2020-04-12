<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\DataAbstractionLayer;

use Shopware\Core\Content\GoogleShopping\Exception\InvalidGoogleIdToken;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class GoogleAccountCredential extends Struct
{
    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var int
     */
    protected $created;

    /**
     * @var string|null
     */
    protected $scope;

    /**
     * @var string
     */
    protected $idToken;

    /**
     * @var int
     */
    protected $expiresIn;

    /**
     * @var string
     */
    protected $refreshToken;

    public function __construct(array $credential = [])
    {
        $this->accessToken = $credential['access_token'];
        $this->refreshToken = $credential['refresh_token'];
        $this->expiresIn = $credential['expires_in'] ?? 0;
        $this->created = $credential['created'] ?? 0;
        $this->idToken = $credential['id_token'];
        $this->scope = $credential['scope'] ?? null;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function setCreated(int $created): void
    {
        $this->created = $created;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function setExpiresIn(int $expiresIn): void
    {
        $this->expiresIn = $expiresIn;
    }

    public function getIdToken(): string
    {
        return $this->idToken;
    }

    public function setIdToken(string $idToken): void
    {
        $this->idToken = $idToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    /**
     * Get token parts to retrieve name and email
     */
    public function getIdTokenParts(): array
    {
        $token = $this->getIdToken();

        if (substr_count($token, '.') === 2) {
            $parts = explode('.', $token);

            return json_decode(base64_decode($parts[1], true), true);
        }

        throw new InvalidGoogleIdToken();
    }

    /**
     * Convert camel case attributes into snake case
     */
    public function normalize(): array
    {
        try {
            $normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());

            return $normalizer->normalize(
                $this,
                null,
                [
                    AbstractNormalizer::ATTRIBUTES => [
                        'accessToken', 'refreshToken', 'created', 'idToken', 'expiresIn', 'scope',
                    ],
                ]
            );
        } catch (ExceptionInterface $e) {
            return [];
        }
    }
}
