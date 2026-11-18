<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Shopware\Core\System\Tag\TagDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminTagController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilterTagIdsService $filterTagIdsService,
        private readonly AclCriteriaValidator $criteriaValidator,
    ) {
    }

    #[Route(
        path: '/api/_admin/tag-filter-ids',
        name: 'api.admin.tag-filter-ids',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['tag:read'], PlatformRequest::ATTRIBUTE_ENTITY => TagDefinition::ENTITY_NAME],
        methods: [Request::METHOD_POST]
    )]
    public function filterIds(Request $request, Criteria $criteria, Context $context): JsonResponse
    {
        // Filters, sortings and aggregations on associations must not reveal data the user cannot read
        $missing = $this->criteriaValidator->validate(TagDefinition::ENTITY_NAME, $criteria, $context);
        if ($missing !== []) {
            throw ApiException::missingPrivileges($missing);
        }

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        return new JsonResponse([
            'total' => $filteredTagIdsStruct->getTotal(),
            'ids' => $filteredTagIdsStruct->getIds(),
        ]);
    }
}
