<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ThemeFileCopyException extends ShopwareHttpException
{
    public function __construct(string $themeName, string $message = '')
    {
        parent::__construct(
            'Unable to move the files of theme "{{ themeName }}". {{ message }}',
            [
                'themeName' => $themeName,
                'message' => $message,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'THEME__FILE_COPY_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
