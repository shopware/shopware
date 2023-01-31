<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class StorefrontResponse extends Response
{
    /**
     * @deprecated tag:v6.6.0 - $data will be natively typed to array and initilized with `[]`
     *
     * @var array
     */
    protected $data;

    /**
     * @deprecated tag:v6.6.0 - $context will be natively typed as `?SalesChannelContext` and initialized with `null`
     *
     * @var SalesChannelContext|null
     */
    protected $context;

    public function getData(): array
    {
        /** @deprecated tag:v6.6.0 - null check can be removed if $data is natively typed */
        if ($this->data === null) {
            return [];
        }

        return $this->data;
    }

    /**
     * @deprecated tag:v6.6.0 - parameter `$data` will be strictly typed to `array`
     */
    public function setData(?array $data): void
    {
        if ($data === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.6.0.0',
                sprintf('Parameter "data" in method "setData" will be strictly typed to "array" in class "%s".', static::class)
            );
        }

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
