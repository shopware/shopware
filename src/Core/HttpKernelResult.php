<?php declare(strict_types=1);

namespace Shopware\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package core
 */
class HttpKernelResult
{
    public function __construct(protected Request $request, protected Response $response)
    {
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
