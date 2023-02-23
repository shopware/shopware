<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
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
}
