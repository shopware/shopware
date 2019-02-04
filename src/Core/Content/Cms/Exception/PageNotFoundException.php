<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PageNotFoundException extends ShopwareHttpException
{
    public function __construct(string $pageId, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Page with id %s was not found.', $pageId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
