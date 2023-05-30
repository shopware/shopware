<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('merchant-services')]
class VariantTypesNotAllowedException extends ShopwareHttpException
{
    /**
     * @param array{variantType: string, extensionName: string, extensionId: int}[] $typeViolations
     */
    public function __construct(array $typeViolations)
    {
        $message = 'The variant types of the following cart positions are not allowed:';

        foreach ($typeViolations as $typeViolation) {
            $message .= sprintf("\nType \"%s\" for \"%s\" (ID: %d)", $typeViolation['variantType'], $typeViolation['extensionName'], $typeViolation['extensionId']);
        }

        parent::__construct($message, $typeViolations);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__NOT_ALLOWED_VARIANT_TYPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
