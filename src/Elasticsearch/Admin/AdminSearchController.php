<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
final class AdminSearchController
{
    private AdminSearcher $searcher;

    private JsonEntityEncoder $entityEncoder;

    private DefinitionInstanceRegistry $definitionRegistry;

    /**
     * @internal
     */
    public function __construct(
        AdminSearcher $searcher,
        DefinitionInstanceRegistry $definitionRegistry,
        JsonEntityEncoder $entityEncoder
    ) {
        $this->searcher = $searcher;
        $this->definitionRegistry = $definitionRegistry;
        $this->entityEncoder = $entityEncoder;
    }

    /**
     * @Since("6.4.12.0")
     * @Route("/api/_admin/es-search", name="api.admin.es-search", methods={"POST"}, defaults={"_routeScope"={"administration"}})
     */
    public function elastic(Request $request, Context $context): Response
    {
        $term = trim($request->get('term', ''));
        $entities = $request->request->all('entities');

        if (empty($term)) {
            throw new \RuntimeException('Search term is empty');
        }

        $results = $this->searcher->search($term, $entities, $context);

        foreach ($results as $entityName => $result) {
            $definition = $this->definitionRegistry->getByEntityName($entityName);

            /** @var EntityCollection<Entity> $entityCollection */
            $entityCollection = $result['data'];
            $entities = [];

            foreach ($entityCollection->getElements() as $key => $entity) {
                $entities[$key] = $this->entityEncoder->encode(new Criteria(), $definition, $entity, '/api');
            }

            $results[$entityName]['data'] = $entities;
        }

        return new JsonResponse(['data' => $results]);
    }
}
