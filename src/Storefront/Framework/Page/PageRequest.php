<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Symfony\Component\HttpFoundation\Request;

class PageRequest implements PageRequestInterface
{
    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @return Request
     */
    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    /**
     * @param Request $httpRequest
     */
    public function setHttpRequest(Request $httpRequest): void
    {
        $this->httpRequest = $httpRequest;
    }
}
