<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Api;

use Shopware\Core\Checkout\Promotion\Util\PromotionCodesLoader;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodesRemover;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PromotionActionController extends AbstractController
{
    /**
     * @var PromotionCodesLoader
     */
    private $codesLoader;

    /**
     * @var PromotionCodesRemover
     */
    private $codesRemover;

    public function __construct(PromotionCodesLoader $codesLoader, PromotionCodesRemover $codesRemover)
    {
        $this->codesLoader = $codesLoader;
        $this->codesRemover = $codesRemover;
    }

    /**
     * @Route("/api/v{version}/_action/promotion/{promotionId}/codes/individual", name="api.action.promotion.codes", methods={"GET"})
     *
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     *
     * @return JsonResponse
     */
    public function getIndividualCodes(string $promotionId, Context $context)
    {
        return new JsonResponse($this->codesLoader->loadIndividualCodes($promotionId));
    }

    /**
     * @Route("/api/v{version}/_action/promotion/{promotionId}/codes/individual", name="api.action.promotion.codes.remove", methods={"DELETE"})
     *
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     *
     * @return JsonResponse
     */
    public function deleteIndividualCodes(string $promotionId, Context $context)
    {
        $this->codesRemover->removeIndividualCodes($promotionId, $context);

        return new JsonResponse([]);
    }
}
