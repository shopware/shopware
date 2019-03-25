<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidTemplateException extends ShopwareHttpException
{
    public function __construct(string $message, $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
