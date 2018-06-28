<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UnknownRepositoryVersionException extends ShopwareHttpException
{
    /**
     * {@inheritdoc}
     */
    public function __construct(string $entityName, int $version, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('There is no "v%d" version of the "%s" repository.', $version, $entityName);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
