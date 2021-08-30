<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Service\AdminSearcher;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal (flag:FEATURE_NEXT_6040)
 */
class AdminSearchController extends AbstractController
{
    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private AdminSearcher $searcher;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private Serializer $serializer;

    private AclCriteriaValidator $criteriaValidator;

    public function __construct(
        RequestCriteriaBuilder $requestCriteriaBuilder,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        AdminSearcher $searcher,
        Serializer $serializer,
        AclCriteriaValidator $criteriaValidator
    ) {
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->searcher = $searcher;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->serializer = $serializer;
        $this->criteriaValidator = $criteriaValidator;
    }

    /**
     * @Since("6.4.5.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/search", name="api.admin.search", methods={"POST"})
     */
    public function search(Request $request, Context $context): Response
    {
        $entities = $this->buildSearchEntities($request, $context);

        $violations = [];

        foreach ($entities as $entity => $criteria) {
            $missing = $this->criteriaValidator->validate($entity, $criteria, $context);

            if (!empty($missing)) {
                $violations[$entity] = (new MissingPrivilegeException($missing))->getErrors()->current();
                unset($entities[$entity]);
            }
        }

        $results = $this->searcher->search($entities, $context);

        return new JsonResponse(['data' => array_merge($results, $violations)]);
    }

    private function buildSearchEntities(Request $request, Context $context): array
    {
        $entities = [];

        $queries = $this->serializer->decode($request->getContent(), 'json');

        foreach ($queries as $entityName => $query) {
            if (!$this->definitionInstanceRegistry->has($entityName)) {
                continue;
            }

            $definition = $this->definitionInstanceRegistry->getByEntityName($entityName);

            $criteriaRequest = $request->duplicate($request->query->all(), $query);

            $entities[$entityName] = $this->requestCriteriaBuilder->handleRequest($criteriaRequest, new Criteria(), $definition, $context);
        }

        return $entities;
    }
}
