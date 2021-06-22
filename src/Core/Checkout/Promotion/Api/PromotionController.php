<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Api;

use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class PromotionController extends AbstractController
{
    /**
     * @var PromotionCodeService
     */
    private $codeService;

    public function __construct(PromotionCodeService $codeService)
    {
        $this->codeService = $codeService;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/promotion/codes/generate-fixed", name="api.action.promotion.codes.generate-fixed", methods={"GET"})
     * @Acl({"promotion.editor"})
     *
     * @throws NotFoundHttpException
     */
    public function generateFixedCode(): Response
    {
        return new JsonResponse($this->codeService->getFixedCode());
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/promotion/codes/generate-individual", name="api.action.promotion.codes.generate-individual", methods={"GET"})
     * @Acl({"promotion.editor"})
     *
     * @throws NotFoundHttpException
     */
    public function generateIndividualCodes(Request $request): Response
    {
        $codePattern = (string) $request->query->get('codePattern');
        if ($codePattern === '') {
            throw new MissingRequestParameterException('codePattern');
        }
        $amount = $request->query->getInt('amount');

        return new JsonResponse($this->codeService->generateIndividualCodes($codePattern, $amount));
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/promotion/codes/replace-individual", name="api.action.promotion.codes.replace-individual", methods={"PATCH"})
     * @Acl({"promotion.editor"})
     *
     * @throws NotFoundHttpException
     */
    public function replaceIndividualCodes(Request $request, Context $context): Response
    {
        $promotionId = (string) $request->request->get('promotionId');
        $codePattern = (string) $request->request->get('codePattern');
        $amount = $request->request->getInt('amount');

        $this->codeService->replaceIndividualCodes($promotionId, $codePattern, $amount, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/promotion/codes/add-individual", name="api.action.promotion.codes.add-individual", methods={"POST"})
     * @Acl({"promotion.editor"})
     *
     * @throws NotFoundHttpException
     */
    public function addIndividualCodes(Request $request, Context $context): Response
    {
        $promotionId = (string) $request->request->get('promotionId');
        $amount = $request->request->getInt('amount');

        $this->codeService->addIndividualCodes($promotionId, $amount, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/promotion/codes/preview", name="api.action.promotion.codes.preview", methods={"GET"})
     * @Acl({"promotion.editor"})
     *
     * @throws NotFoundHttpException
     */
    public function getCodePreview(Request $request): Response
    {
        $codePattern = (string) $request->query->get('codePattern');
        if ($codePattern === '') {
            throw new MissingRequestParameterException('codePattern');
        }

        return new JsonResponse($this->codeService->getPreview($codePattern));
    }
}
