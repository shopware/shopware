<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package storefront
 */
class HttpCacheGenerateKeyEvent extends Event
{
    public function __construct(private readonly Request $request, private string $hash)
    {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }
}
