<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter\Exceptions;

use Shopware\Core\Framework\ShopwareHttpException;

class ApiConversionNotAllowedException extends ShopwareHttpException
{
    public function __construct(string $entityName, int $apiVersion)
    {
        parent::__construct(
            'You entity {{ entity }} is not available or deprecated in api version {{ apiVersion }}.',
            [
                'entity' => $entityName,
                'apiVersion' => $apiVersion,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_CONVERSION_NOT_ALLOWED';
    }
}
