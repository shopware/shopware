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
}
