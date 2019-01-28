<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingOrderRelationException extends ShopwareHttpException
{
    protected $code = 'MISSING-ORDER-RELATION';

    public function __construct(string $relation, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The required relation "%s" is missing .', $relation);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
