<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\Helper;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FakeTokenGenerator
{
    private ?string $secret = 'fake-secret';

    /**
     * @var array<int, string>|null
     */
    private ?array $audience = ['fake-audience'];

    private ?string $issuer = 'fake-issuer';

    private ?string $subject = 'fake-subject';

    private ?string $email = 'fake@email.com';

    private ?string $preferredUsername = 'preferred_username';

    private ?string $givenName = 'given_name';

    private ?string $familyName = 'family_name';

    private ?\DateTimeImmutable $issuedAt;

    private ?\DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable();
    }

    /**
     * @return non-empty-string
     */
    public function generate(?string $kid = null): string
    {
        $headerArray = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        if ($kid !== null) {
            $headerArray['kid'] = $kid;
        }

        $header = self::base64UrlEncode(\json_encode($headerArray, \JSON_THROW_ON_ERROR));
        $content = self::base64UrlEncode((string) json_encode([
            'aud' => $this->audience,
            'iss' => $this->issuer,
            'iat' => $this->issuedAt?->getTimestamp(),
            'exp' => $this->expiresAt?->getTimestamp(),
            'sub' => $this->subject,
            'email' => $this->email,
            'preferred_username' => $this->preferredUsername,
            'given_name' => $this->givenName,
            'family_name' => $this->familyName,
        ], \JSON_THROW_ON_ERROR));

        $singing = $this->base64UrlEncode(hash_hmac('sha256', $header . $content, (string) $this->secret, true));

        return \implode('.', [$header, $content, $singing]);
    }

    public function setSecret(?string $secret): FakeTokenGenerator
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @param array<int, string>|null $audience
     */
    public function setAudience(?array $audience): FakeTokenGenerator
    {
        $this->audience = $audience;

        return $this;
    }

    public function setIssuer(?string $issuer): FakeTokenGenerator
    {
        $this->issuer = $issuer;

        return $this;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): FakeTokenGenerator
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): FakeTokenGenerator
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function setSubject(?string $subject): FakeTokenGenerator
    {
        $this->subject = $subject;

        return $this;
    }

    public function setEmail(?string $email): FakeTokenGenerator
    {
        $this->email = $email;

        return $this;
    }

    public function setPreferredUsername(?string $preferredUsername): FakeTokenGenerator
    {
        $this->preferredUsername = $preferredUsername;

        return $this;
    }

    public function setGivenName(?string $givenName): FakeTokenGenerator
    {
        $this->givenName = $givenName;

        return $this;
    }

    public function setFamilyName(?string $familyName): FakeTokenGenerator
    {
        $this->familyName = $familyName;

        return $this;
    }

    private function base64UrlEncode(string $text): string
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }
}
