<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package storefront
 */
#[Package('storefront')]
class HttpCacheGenerateKeyEvent extends Event
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $hash;

    public function __construct(Request $request, string $hash)
    {
        $this->request = $request;
        $this->hash = $hash;
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
