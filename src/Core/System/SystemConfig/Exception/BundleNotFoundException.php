<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BundleNotFoundException extends ShopwareHttpException
{
    protected $code = 'BUNDLE-NOT-FOUND';

    public function __construct(string $bundleName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Bundle by name "%s" not found', $bundleName);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
