<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class IncrementException extends HttpException
{
    public const KEY_PARAMETER_IS_MISSING = 'FRAMEWORK__KEY_PARAMETER_IS_MISSING';
    public const CLUSTER_PARAMETER_IS_MISSING = 'FRAMEWORK__CLUSTER_PARAMETER_IS_MISSING';
    public const WRONG_GATEWAY_TYPE = 'FRAMEWORK__INCREMENT_WRONG_GATEWAY_TYPE';
    public const GATEWAY_SERVICE_NOT_FOUND = 'FRAMEWORK__INCREMENT_GATEWAY_SERVICE_NOT_FOUND';
    public const WRONG_GATEWAY_CLASS = 'FRAMEWORK__INCREMENT_WRONG_GATEWAY_CLASS';

    public static function keyParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::KEY_PARAMETER_IS_MISSING,
            'Parameter "key" is missing.',
        );
    }

    public static function clusterParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CLUSTER_PARAMETER_IS_MISSING,
            'Parameter "cluster" is missing.',
        );
    }

    public static function gatewayNotFound(string $pool): ShopwareHttpException
    {
        return new IncrementGatewayNotFoundException($pool);
    }

    public static function wrongGatewayType(string $pool): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::WRONG_GATEWAY_TYPE,
            'shopware.increment.gateway type of {{ pool }} pool must be a string',
            [
                'pool' => $pool,
            ]
        );
    }

    public static function gatewayServiceNotFound(string $type, string $pool, string $serviceId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::GATEWAY_SERVICE_NOT_FOUND,
            'Can not find increment gateway for configured type {{ type }} of pool {{ pool }}, expected service id {{ serviceId }} can not be found',
            [
                'type' => $type,
                'pool' => $pool,
                'serviceId' => $serviceId,
            ]
        );
    }

    public static function wrongGatewayClass(string $serviceId, string $requiredClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::WRONG_GATEWAY_CLASS,
            'Increment gateway with id {{ serviceId }}, expected service instance of {{ requiredClass }}',
            [
                'serviceId' => $serviceId,
                'requiredClass' => $requiredClass,
            ]
        );
    }
}
