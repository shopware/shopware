<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidThemeConfigException extends ShopwareHttpException
{
    public function __construct(string $fieldName, ?\Throwable $previous = null)
    {
        parent::__construct('Unable to find setter for config field "{{ fieldName }}"', ['fieldName' => $fieldName], $previous);
    }

    public function getErrorCode(): string
    {
        return 'THEME__INVALID_THEME_CONFIG';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
