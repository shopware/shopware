<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

#[Package('storefront')]
class Page extends Struct
{
    /**
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     *
     * @var HeaderPagelet|null
     */
    protected $header;

    /**
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     *
     * @var FooterPagelet|null
     */
    protected $footer;

    /**
     * @deprecated tag:v6.7.0 - Will be removed, no more needed
     *
     * @var ShippingMethodCollection
     */
    protected $salesChannelShippingMethods;

    /**
     * @deprecated tag:v6.7.0 - Will be removed, no more needed
     *
     * @var PaymentMethodCollection
     */
    protected $salesChannelPaymentMethods;

    /**
     * @var MetaInformation
     */
    protected $metaInformation;

    /**
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     */
    public function getHeader(): ?HeaderPagelet
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->header;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     */
    public function setHeader(?HeaderPagelet $header): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->header = $header;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     */
    public function getFooter(): ?FooterPagelet
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->footer;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     */
    public function setFooter(?FooterPagelet $footer): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->footer = $footer;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, no more needed
     */
    public function getSalesChannelShippingMethods(): ?ShippingMethodCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->salesChannelShippingMethods;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, no more needed
     */
    public function setSalesChannelShippingMethods(ShippingMethodCollection $salesChannelShippingMethods): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->salesChannelShippingMethods = $salesChannelShippingMethods;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, no more needed
     */
    public function getSalesChannelPaymentMethods(): ?PaymentMethodCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->salesChannelPaymentMethods;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, no more needed
     */
    public function setSalesChannelPaymentMethods(PaymentMethodCollection $salesChannelPaymentMethods): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->salesChannelPaymentMethods = $salesChannelPaymentMethods;
    }

    public function getMetaInformation(): ?MetaInformation
    {
        return $this->metaInformation;
    }

    public function setMetaInformation(MetaInformation $metaInformation): void
    {
        $this->metaInformation = $metaInformation;
    }
}
