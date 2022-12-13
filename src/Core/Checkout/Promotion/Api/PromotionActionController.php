<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Api;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"api"}})
 */
class PromotionActionController extends AbstractController
{
    private LineItemGroupServiceRegistry $serviceRegistry;

    /**
     * @internal
     */
    public function __construct(LineItemGroupServiceRegistry $serviceRegistry)
    {
        $this->serviceRegistry = $serviceRegistry;
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
}
