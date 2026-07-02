<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AdminAuthException extends HttpException
{
    public const ENCRYPTION_FAILED = 'FRAMEWORK__ADMIN_AUTH_ENCRYPTION_FAILED';
    public const DECRYPTION_FAILED = 'FRAMEWORK__ADMIN_AUTH_DECRYPTION_FAILED';

    public static function encryptionFailed(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENCRYPTION_FAILED,
            'Failed to encrypt secret.'
        );
    }

    public static function decryptionFailed(?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DECRYPTION_FAILED,
            'Failed to decrypt secret.',
            [],
            $previous
        );
    }
}
