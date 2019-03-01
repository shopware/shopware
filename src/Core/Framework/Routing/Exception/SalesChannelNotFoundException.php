<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelNotFoundException extends ShopwareHttpException
{
    protected $code = 'SALES-CHANNEL-NOT-FOUND';

    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        $message = 'The sales channel was not found.';

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }
}
