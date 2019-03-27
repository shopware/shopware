<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class BundleNotFoundException extends ShopwareHttpException
{
    public function __construct(string $bundleName)
    {
        parent::__construct(
            'Bundle by name "{{ bundle }}" not found.',
            ['bundle' => $bundleName]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__BUNDLE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
