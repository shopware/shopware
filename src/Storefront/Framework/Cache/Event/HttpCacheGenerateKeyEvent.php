<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class HttpCacheGenerateKeyEvent extends Event
{
    /**
     * @var string
     */
    private $hash;

    /**
     * @var Request
     */
    private $request;

    public function __construct(string $hash, Request $request)
    {
        $this->hash = $hash;
        $this->request = $request;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash)
    {
        $this->hash = $hash;
        return $this->hash;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
