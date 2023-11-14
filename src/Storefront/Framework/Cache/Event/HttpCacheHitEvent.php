<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Psr\Cache\CacheItemInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - Will be removed, use `Shopware\Core\Framework\Adapter\Cache\Http\Event\HttpCacheHitEvent` instead
 */
#[Package('core')]
class HttpCacheHitEvent extends Event
{
    public function __construct(
        private readonly CacheItemInterface $item,
        private readonly Request $request,
        private readonly Response $response
    ) {
    }

    public function getItem(): CacheItemInterface
    {
        return $this->item;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
