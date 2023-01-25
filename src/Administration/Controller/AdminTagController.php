<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['administration']])]
#[Package('administration')]
class AdminTagController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly FilterTagIdsService $filterTagIdsService)
    {
    }

    #[Route(path: '/api/_admin/tag-filter-ids', name: 'api.admin.tag-filter-ids', defaults: ['_acl' => ['tag:read'], '_entity' => 'tag'], methods: ['POST'])]
    public function filterIds(Request $request, Criteria $criteria, Context $context): JsonResponse
    {
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        return new JsonResponse([
            'total' => $filteredTagIdsStruct->getTotal(),
            'ids' => $filteredTagIdsStruct->getIds(),
        ]);
    }
}
