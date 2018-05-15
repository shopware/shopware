<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Firewall;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthenticationFailureListener
{
    public function onAuthenticationFailureResponse(): void
    {
        throw new UnauthorizedHttpException('Bearer', 'Bad credentials, please verify that your username/password are correctly set.');
    }

    public function onJWTExpired(): void
    {
        throw new UnauthorizedHttpException('Bearer', 'Your token is expired, please renew it.');
    }

    public function onJWTInvalid(): void
    {
        throw new UnauthorizedHttpException('Bearer', 'Your token is invalid, please request a new one.');
    }

    public function onJWTNotFound(): void
    {
        throw new UnauthorizedHttpException('Bearer', 'Please provide a valid token.');
    }
}
