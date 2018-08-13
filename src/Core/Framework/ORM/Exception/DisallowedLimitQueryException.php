<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: pw
 * Date: 13.08.18
 * Time: 11:39
 */

namespace Shopware\Core\Framework\ORM\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DisallowedLimitQueryException extends ShopwareHttpException
{
    public function __construct(array $allowedLimits, $limit, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The limit must be one of the "allowed_limits" [%s]. Given: %s', implode(', ', $allowedLimits), $limit);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
