<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Api;

use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('checkout')]
class PromotionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly PromotionCodeService $codeService)
    {
    }

    #[Route(
        path: '/api/_action/promotion/codes/generate-fixed',
        name: 'api.action.promotion.codes.generate-fixed',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion.editor']],
        methods: [Request::METHOD_GET]
    )]
    public function generateFixedCode(): Response
    {
        return new JsonResponse($this->codeService->getFixedCode());
    }

    #[Route(
        path: '/api/_action/promotion/codes/generate-individual',
        name: 'api.action.promotion.codes.generate-individual',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion.editor']],
        methods: [Request::METHOD_GET]
    )]
    public function generateIndividualCodes(Request $request): Response
    {
        $codePattern = (string) $request->query->get('codePattern');
        if ($codePattern === '') {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw RoutingException::missingRequestParameter('codePattern'); // @phpstan-ignore-line shopware.domainException
            }
            throw PromotionException::missingRequestParameter('codePattern');
        }
        $amount = $request->query->getInt('amount');

        return new JsonResponse($this->codeService->generateIndividualCodes($codePattern, $amount));
    }

    #[Route(
        path: '/api/_action/promotion/codes/replace-individual',
        name: 'api.action.promotion.codes.replace-individual',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion.editor']],
        methods: [Request::METHOD_PATCH]
    )]
    public function replaceIndividualCodes(Request $request, Context $context): Response
    {
        $promotionId = (string) $request->request->get('promotionId');
        $codePattern = (string) $request->request->get('codePattern');
        $amount = $request->request->getInt('amount');

        $this->codeService->replaceIndividualCodes($promotionId, $codePattern, $amount, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/promotion/codes/add-individual',
        name: 'api.action.promotion.codes.add-individual',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function addIndividualCodes(Request $request, Context $context): Response
    {
        $promotionId = (string) $request->request->get('promotionId');
        $amount = $request->request->getInt('amount');

        $this->codeService->addIndividualCodes($promotionId, $amount, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/promotion/codes/preview',
        name: 'api.action.promotion.codes.preview',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['promotion.editor']],
        methods: [Request::METHOD_GET]
    )]
    public function getCodePreview(Request $request): Response
    {
        $codePattern = (string) $request->query->get('codePattern');
        if ($codePattern === '') {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw RoutingException::missingRequestParameter('codePattern'); // @phpstan-ignore-line shopware.domainException
            }
            throw PromotionException::missingRequestParameter('codePattern');
        }

        return new JsonResponse($this->codeService->getPreview($codePattern));
    }
}
