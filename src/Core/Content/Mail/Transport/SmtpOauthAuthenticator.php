<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Transport;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @internal
 */
#[Package('services-settings')]
class SmtpOauthAuthenticator implements AuthenticatorInterface
{
    private const SMTP_RESPONSE_CODE_AUTH_SUCCESS = 235;

    public function __construct(private readonly SmtpOauthTokenProvider $tokenProvider)
    {
    }

    public function getAuthKeyword(): string
    {
        return 'XOAUTH2';
    }

    public function authenticate(EsmtpTransport $client): void
    {
        $client->executeCommand('AUTH XOAUTH2 ' . base64_encode('user=' . $client->getUsername() . "\1auth=Bearer " . $this->tokenProvider->getToken() . "\1\1") . "\r\n", [self::SMTP_RESPONSE_CODE_AUTH_SUCCESS]);
    }
}
