<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - Will be removed
 */
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
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

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
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        $this->data = $data;
    }

    public function getContext(): ?SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        return $this->context;
    }

    public function setContext(?SalesChannelContext $context): void
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));
        $this->context = $context;
    }
}
