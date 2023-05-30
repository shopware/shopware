<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Framework\Search\CriteriaCollection;
use Shopware\Administration\Service\AdminSearcher;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

#[Package('administration')]
class AdminSearchController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestCriteriaBuilder $requestCriteriaBuilder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly AdminSearcher $searcher,
        private readonly DecoderInterface $serializer,
        private readonly AclCriteriaValidator $criteriaValidator,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly JsonEntityEncoder $entityEncoder
    ) {
    }

    #[Route(path: '/api/_admin/search', name: 'api.admin.search', defaults: ['_routeScope' => ['administration']], methods: ['POST'])]
    public function search(Request $request, Context $context): Response
    {
        $criteriaCollection = $this->buildSearchEntities($request, $context);

        $violations = [];

        foreach ($criteriaCollection as $entity => $criteria) {
            $missing = $this->criteriaValidator->validate($entity, $criteria, $context);

            if (!empty($missing)) {
                $violations[$entity] = (new MissingPrivilegeException($missing))->getErrors()->current();
                $criteriaCollection->remove($entity);
            }
        }

        $results = $this->searcher->search($criteriaCollection, $context);

        foreach ($results as $entityName => $result) {
            if (!$criteriaCollection->has($entityName)) {
                continue;
            }

            /** @var Criteria $criteria */
            $criteria = $criteriaCollection->get($entityName);
            $definition = $this->definitionRegistry->getByEntityName($entityName);

            /** @var EntityCollection<Entity> $entityCollection */
            $entityCollection = $result['data'];
            $entities = [];

            foreach ($entityCollection->getElements() as $key => $entity) {
                $entities[$key] = $this->entityEncoder->encode($criteria, $definition, $entity, '/api');
            }

            $results[$entityName]['data'] = $entities;
        }

        return new JsonResponse(['data' => array_merge($results, $violations)]);
    }

    private function buildSearchEntities(Request $request, Context $context): CriteriaCollection
    {
        $collection = new CriteriaCollection();

        $queries = $this->serializer->decode($request->getContent(), 'json');

        foreach ($queries as $entityName => $query) {
            if (!$this->definitionInstanceRegistry->has($entityName)) {
                continue;
            }

            $definition = $this->definitionInstanceRegistry->getByEntityName($entityName);

            $criteriaRequest = $request->duplicate($request->query->all(), $query);

            $criteria = $this->requestCriteriaBuilder->handleRequest($criteriaRequest, new Criteria(), $definition, $context);

            $collection->set($entityName, $criteria);
        }

        return $collection;
    }
}
