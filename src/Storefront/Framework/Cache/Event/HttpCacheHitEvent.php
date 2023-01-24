<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package storefront
 */
class HttpCacheHitEvent extends Event
{
    public function __construct(private readonly CacheItemInterface $item, private readonly Request $request, private readonly Response $response)
    {
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
