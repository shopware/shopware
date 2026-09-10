<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ShopSettingsRoute extends AbstractShopSettingsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getDecorated(): AbstractShopSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/shop-settings',
        name: 'store-api.shop-settings',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(SalesChannelContext $context): ShopSettingsRouteResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        $basicInformation = $this->loadDomain('core.basicInformation', $salesChannelId);

        $settings = new ShopSettings(
            general: ShopGeneralSettings::fromConfig($basicInformation),
            contactForm: ShopContactFormSettings::fromConfig($basicInformation),
            loginRegistration: ShopLoginRegistrationSettings::fromConfig($this->loadDomain('core.loginRegistration', $salesChannelId)),
            cart: ShopCartSettings::fromConfig($this->loadDomain('core.cart', $salesChannelId)),
            listing: ShopListingSettings::fromConfig($this->loadDomain('core.listing', $salesChannelId)),
            newsletter: ShopNewsletterSettings::fromConfig($this->loadDomain('core.newsletter', $salesChannelId)),
        );

        return new ShopSettingsRouteResponse($settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDomain(string $domain, string $salesChannelId): array
    {
        $config = $this->systemConfigService->get($domain, $salesChannelId);

        return \is_array($config) ? $config : [];
    }
}
