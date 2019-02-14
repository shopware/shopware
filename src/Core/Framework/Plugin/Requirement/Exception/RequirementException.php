<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

abstract class RequirementException extends ShopwareHttpException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_FAILED_DEPENDENCY;
    }
}
