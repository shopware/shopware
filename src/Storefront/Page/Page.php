<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

#[Package('framework')]
class Page extends Struct
{
    /**
     * @var HeaderPagelet|null
     *
     * @deprecated tag:v6.7.0 - Will be removed, header is loaded via esi and will be rendered in an separate request
     */
    protected $header;

    /**
     * @var FooterPagelet|null
     *
     * @deprecated tag:v6.7.0 - Will be removed, footer is loaded via esi and will be rendered in an separate request
     */
    protected $footer;

    /**
     * @var ShippingMethodCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be removed, as it is not needed anymore
     */
    protected $salesChannelShippingMethods;

    /**
     * @var PaymentMethodCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be removed, as it is not needed anymore
     */
    protected $salesChannelPaymentMethods;

    /**
     * @var MetaInformation|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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
     * @deprecated tag:v6.7.0 - Will be removed, as it is not needed anymore
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
     * @deprecated tag:v6.7.0 - Will be removed, as it is not needed anymore
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
     * @deprecated tag:v6.7.0 - Will be removed, as it is not needed anymore
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
     * @deprecated tag:v6.7.0 - Will be removed, as it is not needed anymore
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
