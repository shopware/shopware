<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"administration"}})
 *
 * @package administration
 */
class AdminTagController extends AbstractController
{
    private FilterTagIdsService $filterTagIdsService;

    /**
     * @internal
     */
    public function __construct(FilterTagIdsService $filterTagIdsService)
    {
        $this->filterTagIdsService = $filterTagIdsService;
    }

    /**
     * @Since("6.4.10.1")
     * @Route("/api/_admin/tag-filter-ids", name="api.admin.tag-filter-ids", methods={"POST"}, defaults={"_acl"={"tag:read"}, "_entity"="tag"})
     */
    public function filterIds(Request $request, Criteria $criteria, Context $context): JsonResponse
    {
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        return new JsonResponse([
            'total' => $filteredTagIdsStruct->getTotal(),
            'ids' => $filteredTagIdsStruct->getIds(),
        ]);
    }
}
