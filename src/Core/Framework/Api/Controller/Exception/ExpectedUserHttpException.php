<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use ApiException::userNotLoggedIn instead
 */
#[Package('core')]
class ExpectedUserHttpException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('For this interaction an authenticated user login is required.');
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ApiException::getErrorCode')
        );

        return 'FRAMEWORK__EXPECTED_USER';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ApiException::getStatusCode')
        );

        return Response::HTTP_FORBIDDEN;
    }
}
