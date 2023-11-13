<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Feature\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class FeatureNotActiveException extends ShopwareHttpException
{
    public function __construct(
        string $feature,
        ?\Throwable $previous = null
    ) {
        $message = sprintf('This function can only be used with feature flag %s', $feature);
        parent::__construct($message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FEATURE_NOT_ACTIVE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
