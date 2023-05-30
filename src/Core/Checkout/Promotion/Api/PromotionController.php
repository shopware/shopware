<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Api;

use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class PromotionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly PromotionCodeService $codeService)
    {
    }

    #[Route(path: '/api/_action/promotion/codes/generate-fixed', name: 'api.action.promotion.codes.generate-fixed', methods: ['GET'], defaults: ['_acl' => ['promotion.editor']])]
    public function generateFixedCode(): Response
    {
        return new JsonResponse($this->codeService->getFixedCode());
    }

    #[Route(path: '/api/_action/promotion/codes/generate-individual', name: 'api.action.promotion.codes.generate-individual', methods: ['GET'], defaults: ['_acl' => ['promotion.editor']])]
    public function generateIndividualCodes(Request $request): Response
    {
        $codePattern = (string) $request->query->get('codePattern');
        if ($codePattern === '') {
            throw RoutingException::missingRequestParameter('codePattern');
        }
        $amount = $request->query->getInt('amount');

        return new JsonResponse($this->codeService->generateIndividualCodes($codePattern, $amount));
    }

    #[Route(path: '/api/_action/promotion/codes/replace-individual', name: 'api.action.promotion.codes.replace-individual', methods: ['PATCH'], defaults: ['_acl' => ['promotion.editor']])]
    public function replaceIndividualCodes(Request $request, Context $context): Response
    {
        $promotionId = (string) $request->request->get('promotionId');
        $codePattern = (string) $request->request->get('codePattern');
        $amount = $request->request->getInt('amount');

        $this->codeService->replaceIndividualCodes($promotionId, $codePattern, $amount, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/promotion/codes/add-individual', name: 'api.action.promotion.codes.add-individual', methods: ['POST'], defaults: ['_acl' => ['promotion.editor']])]
    public function addIndividualCodes(Request $request, Context $context): Response
    {
        $promotionId = (string) $request->request->get('promotionId');
        $amount = $request->request->getInt('amount');

        $this->codeService->addIndividualCodes($promotionId, $amount, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/promotion/codes/preview', name: 'api.action.promotion.codes.preview', methods: ['GET'], defaults: ['_acl' => ['promotion.editor']])]
    public function getCodePreview(Request $request): Response
    {
        $codePattern = (string) $request->query->get('codePattern');
        if ($codePattern === '') {
            throw RoutingException::missingRequestParameter('codePattern');
        }

        return new JsonResponse($this->codeService->getPreview($codePattern));
    }
}
