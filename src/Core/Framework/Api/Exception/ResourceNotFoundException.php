<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ResourceNotFoundException extends ShopwareHttpException
{
    protected $code = 'REST-RESOURCE-4';

    public function __construct(string $resourceType, array $primaryKey, \Exception $previous = null, $code = 0)
    {
        $resourceIds = [];
        foreach ($primaryKey as $key => $value) {
            $resourceIds[] = $key . '(' . $value . ')';
        }

        $message = sprintf('The %s resource with the following primary key was not found: %s', $resourceType, implode(' ', $resourceIds));

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
