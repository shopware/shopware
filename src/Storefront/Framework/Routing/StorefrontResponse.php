<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

class StorefrontResponse extends Response
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var SalesChannelContext|null
     */
    protected $context;

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    public function getContext(): ?SalesChannelContext
    {
        return $this->context;
    }

    public function setContext(?SalesChannelContext $context): void
    {
        $this->context = $context;
    }
}
