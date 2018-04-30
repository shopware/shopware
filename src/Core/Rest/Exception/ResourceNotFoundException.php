<?php declare(strict_types=1);

namespace Shopware\Rest\Exception;

use Shopware\Framework\ShopwareException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceNotFoundException extends NotFoundHttpException implements ShopwareException
{
    protected $code = 'REST-RESOURCE-4';

    public function __construct(string $resourceType, string $resourceId, \Exception $previous = null, $code = 0)
    {
        $message = sprintf('The %s resource with id "%s" was not found.', $resourceType, $resourceId);

        parent::__construct($message, $previous, $code);
    }
}
