<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Psr\Cache\CacheItemInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class HttpCacheItemWrittenEvent extends Event
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        private readonly CacheItemInterface $item,
        private readonly array $tags,
        private readonly Request $request,
        private readonly Response $response
    ) {
    }

    public function getItem(): CacheItemInterface
    {
        return $this->item;
    }

    public function getTags(): array
    {
        return $this->tags;
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
