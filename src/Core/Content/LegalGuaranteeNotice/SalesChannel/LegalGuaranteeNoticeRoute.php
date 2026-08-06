<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LegalGuaranteeNoticeRoute extends AbstractLegalGuaranteeNoticeRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LegalGuaranteeNoticeRenderer $renderer,
    ) {
    }

    public function getDecorated(): AbstractLegalGuaranteeNoticeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/legal-guarantee-notice',
        name: 'store-api.legal-guarantee-notice',
        methods: [Request::METHOD_GET]
    )]
    public function load(SalesChannelContext $context): LegalGuaranteeNoticeRouteResponse
    {
        if (!$this->systemConfigService->getBool('core.cart.showLegalGuaranteeNotice', $context->getSalesChannelId())) {
            return new LegalGuaranteeNoticeRouteResponse(null, null);
        }

        return new LegalGuaranteeNoticeRouteResponse(
            $this->renderer->renderForLanguage($context->getLanguageId()),
            $this->renderer->linkForLanguage($context->getLanguageId()),
        );
    }
}
