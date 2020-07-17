<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

class HttpCacheHitEvent extends Event
{
    /**
     * @var CacheItemInterface
     */
    private $item;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    public function __construct(CacheItemInterface $item, Request $request, Response $response)
    {
        $this->item = $item;
        $this->request = $request;
        $this->response = $response;
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
