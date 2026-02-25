<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ThemeCompileException extends ShopwareHttpException
{
    public function __construct(
        string $themeName,
        string $message = '',
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'Unable to compile the theme "{{ themeName }}". {{ message }}',
            [
                'themeName' => $themeName,
                'message' => $message,
            ],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'THEME__COMPILING_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public static function bundleRelativeFileNotFound(
        string $themeName,
        string $bundleName,
        string $filePath
    ): self {
        return new self(
            $themeName,
            \sprintf(
                'Unable to load file "@%s/%s". File does not exist.',
                $bundleName,
                $filePath
            )
        );
    }

    public static function couldNotCompileTheme(
        string $themeName,
        string $filePath,
        string $reason
    ): self {
        return new self(
            $themeName,
            \sprintf(
                'Unable to load file "Resources/%s". %s',
                $filePath,
                $reason
            )
        );
    }
}
