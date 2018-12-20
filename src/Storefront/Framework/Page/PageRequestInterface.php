<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Symfony\Component\HttpFoundation\Request;

interface PageRequestInterface
{
    /**
     * @return Request
     */
    public function getHttpRequest(): Request;

    /**
     * @param Request $httpRequest
     */
    public function setHttpRequest(Request $httpRequest): void;
}
