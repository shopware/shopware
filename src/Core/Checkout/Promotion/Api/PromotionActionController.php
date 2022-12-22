<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Api;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterServiceRegistry;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodesLoader;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodesRemover;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"api"}})
 */
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

    /**
     * @var LineItemGroupServiceRegistry
     */
    private $serviceRegistry;

    /**
     * @var FilterServiceRegistry
     */
    private $filterServiceRegistry;

    /**
     * @deprecated tag:v6.5.0 - `PromotionCodesLoader`, `PromotionCodesRemover` & `FilterServiceRegistry` are no longer needed
     *
     * @internal
     */
    public function __construct(PromotionCodesLoader $codesLoader, PromotionCodesRemover $codesRemover, LineItemGroupServiceRegistry $serviceRegistry, FilterServiceRegistry $filterServiceRegistry)
    {
        $this->codesLoader = $codesLoader;
        $this->codesRemover = $codesRemover;
        $this->serviceRegistry = $serviceRegistry;
        $this->filterServiceRegistry = $filterServiceRegistry;
    }

    /**
     * @deprecated tag:v6.5.0 - Use PromotionCodeService instead
     * @Since("6.0.0.0")
     * @Route("/api/_action/promotion/{promotionId}/codes/individual", name="api.action.promotion.codes", methods={"GET"}, defaults={"_acl"={"promotion.viewer"}})
     */
    public function getIndividualCodes(string $promotionId, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            '/promotion/{promotionId}/codes/individual route is deprecated, use PromotionCodeService instead'
        );

        return new JsonResponse($this->codesLoader->loadIndividualCodes($promotionId));
    }

    /**
     * @deprecated tag:v6.5.0 - Use PromotionCodeService instead
     * @Since("6.0.0.0")
     * @Route("/api/_action/promotion/{promotionId}/codes/individual", name="api.action.promotion.codes.remove", methods={"DELETE"}, defaults={"_acl"={"promotion.deleter"}})
     */
    public function deleteIndividualCodes(string $promotionId, Context $context): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            '/promotion/{promotionId}/codes/individual route is deprecated, use PromotionCodeService instead'
        );

        $this->codesRemover->removeIndividualCodes($promotionId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/promotion/setgroup/packager", name="api.action.promotion.setgroup.packager", methods={"GET"}, defaults={"_acl"={"promotion.viewer"}})
     */
    public function getSetGroupPackagers(): JsonResponse
    {
        $packagerKeys = [];

        /** @var LineItemGroupPackagerInterface $packager */
        foreach ($this->serviceRegistry->getPackagers() as $packager) {
            $packagerKeys[] = $packager->getKey();
        }

        return new JsonResponse($packagerKeys);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/promotion/setgroup/sorter", name="api.action.promotion.setgroup.sorter", methods={"GET"}, defaults={"_acl"={"promotion.viewer"}})
     */
    public function getSetGroupSorters(): JsonResponse
    {
        $sorterKeys = [];

        /** @var LineItemGroupSorterInterface $sorter */
        foreach ($this->serviceRegistry->getSorters() as $sorter) {
            $sorterKeys[] = $sorter->getKey();
        }

        return new JsonResponse($sorterKeys);
    }

    /**
     * @deprecated tag:v6.5.0 - Use PromotionCodeService instead
     * @Since("6.3.4.0")
     * @Route("/api/_action/promotion/discount/picker", name="api.action.promotion.discount.picker", methods={"GET"}, defaults={"_acl"={"promotion.viewer"}})
     */
    public function getDiscountFilterPickers(): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            '/promotion/discount/picker route is deprecated, use PromotionCodeService instead'
        );

        $pickerKeys = [];

        /** @var FilterPickerInterface $picker */
        foreach ($this->filterServiceRegistry->getPickers() as $picker) {
            $pickerKeys[] = $picker->getKey();
        }

        return new JsonResponse($pickerKeys);
    }
}
