<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Exception;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class ErrorRedirectRequestEvent implements ShopwareEvent
{
    public function __construct(
        private readonly Request $request,
        private readonly \Throwable $exception,
        private readonly Context $context,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
