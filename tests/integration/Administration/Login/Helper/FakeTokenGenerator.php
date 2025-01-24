<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Login\Helper;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class FakeTokenGenerator
{
    private ?string $secret = 'fake-secret';

    private ?array $audience = ['fake-audience'];

    private ?string $issuer = 'fake-issuer';

    private ?string $subject = 'fake-subject';

    private ?string $email = 'fake@email.com';

    private \DateTimeImmutable $issuedAt;

    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable();
    }

    public function generate(): string
    {
        $header = self::base64UrlEncode('{"alg": "HS512","typ": "JWT"}');
        $content = self::base64UrlEncode(json_encode([
            'aud' => $this->audience,
            'iss' => $this->issuer,
            'iat' => $this->issuedAt->getTimestamp(),
            'exp' => $this->expiresAt->getTimestamp(),
            'sub' => $this->subject,
            'email' => $this->email,
        ]));

        $singing = $this->base64UrlEncode(hash_hmac('sha256', $header . $content, $this->secret, true));

        return \implode('.', [$header, $content, $singing]);
    }

    public function setSecret(?string $secret): FakeTokenGenerator
    {
        $this->secret = $secret;

        return $this;
    }

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

    public function setIssuedAt(\DateTimeImmutable $issuedAt): FakeTokenGenerator
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): FakeTokenGenerator
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

    private function base64UrlEncode($text)
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }
}
