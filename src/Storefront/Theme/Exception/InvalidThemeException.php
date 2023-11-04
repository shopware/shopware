<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class InvalidThemeException extends ShopwareHttpException
{
    public function __construct(string $themeName)
    {
        parent::__construct('Unable to find the theme "{{ themeName }}"', ['themeName' => $themeName]);
    }

    public function getErrorCode(): string
    {
        return 'THEME__INVALID_THEME';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
