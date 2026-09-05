<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\Log\Package;

/**
 * Safety net for every Store / FRW Guzzle request.
 *
 * @internal
 */
#[Package('checkout')]
class AirGappedStoreRequestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AirGappedMode $airGappedMode,
    ) {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $this->airGappedMode->denyShopwareOperatedHttp();

            return $handler($request, $options);
        };
    }
}
