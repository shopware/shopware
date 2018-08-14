<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Exception\UnknownRepositoryVersionException;
use Shopware\Core\Framework\Api\OAuth\Api\Scope\WriteScope;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\DefinitionRegistry;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Serializer;

class ApiController extends Controller
{
    public const WRITE_UPDATE = 'update';
    public const WRITE_CREATE = 'create';

    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var EntityWriterInterface
     */
    private $entityWriter;

    /**
     * @var RequestCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        DefinitionRegistry $definitionRegistry,
        Serializer $serializer,
        ResponseFactory $responseFactory,
        EntityWriterInterface $entityWriter,
        RequestCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->serializer = $serializer;
        $this->responseFactory = $responseFactory;
        $this->entityWriter = $entityWriter;

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function detail(Request $request, Context $context, string $path): Response
    {
        $pathSegments = $this->buildEntityPath($path);

        $root = $pathSegments[0]['entity'];
        $id = $pathSegments[\count($pathSegments) - 1]['value'];

        $definition = $this->definitionRegistry->get($root);

        $associations = array_column($pathSegments, 'entity');
        array_shift($associations);

        if (empty($associations)) {
            $repository = $this->getRepository($definition, $request);
        } else {
            /** @var EntityDefinition $definition */
            $field = $this->getAssociation($definition::getFields(), $associations);

            $definition = $field->getReferenceClass();
            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getReferenceDefinition();
            }

            $repository = $this->getRepository($definition, $request);
        }

        /** @var RepositoryInterface $repository */
        $entities = $repository->read(new ReadCriteria([$id]), $context);

        $entity = $entities->get($id);

        if ($entity === null) {
            throw new ResourceNotFoundException($definition::getEntityName(), ['id' => $id]);
        }

        return $this->responseFactory->createDetailResponse($entity, (string) $definition, $request, $context);
    }

    public function search(Request $request, Context $context, string $path): Response
    {
        $result = $this->fetchListing($request, $context, $path);

        $definition = $this->getDefinitionOfPath($path);

        return $this->responseFactory->createListingResponse($result, $definition, $request, $context);
    }

    public function list(Request $request, Context $context, string $path): Response
    {
        $result = $this->fetchListing($request, $context, $path);

        $definition = $this->getDefinitionOfPath($path);

        return $this->responseFactory->createListingResponse($result, $definition, $request, $context);
    }

    public function create(Request $request, Context $context, string $path): Response
    {
        if (!$this->hasScope($request, WriteScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException('You don\'t have write access using this access key.');
        }

        return $this->write($request, $context, $path, self::WRITE_CREATE);
    }

    public function update(Request $request, Context $context, string $path)
    {
        if (!$this->hasScope($request, WriteScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException('You don\'t have write access using this access key.');
        }

        return $this->write($request, $context, $path, self::WRITE_UPDATE);
    }

    public function delete(Request $request, Context $context, string $path): Response
    {
        if (!$this->hasScope($request, WriteScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException('You don\'t have write access using this access key.');
        }

        $pathSegments = $this->buildEntityPath($path);

        $last = $pathSegments[\count($pathSegments) - 1];

        $id = $last['value'];

        $first = array_shift($pathSegments);

        /* @var string|EntityDefinition $definition */
        if (\count($pathSegments) === 0) {
            //first api level call /product/{id}
            $definition = $first['definition'];

            $this->doDelete($request, $context, $definition, $id);

            return $this->responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        $child = array_pop($pathSegments);
        $parent = $first;
        if (!empty($pathSegments)) {
            $parent = array_pop($pathSegments);
        }

        $definition = $child['definition'];

        /** @var AssociationInterface $association */
        $association = $child['field'];

        if ($association instanceof OneToManyAssociationField) {
            $this->doDelete($request, $context, $definition, $id);

            return $this->responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        // DELETE api/product/{id}/manufacturer/{id}
        if ($association instanceof ManyToOneAssociationField) {
            $this->doDelete($request, $context, $definition, $id);

            return $this->responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        // DELETE api/product/{id}/category/{id}
        if ($association instanceof ManyToManyAssociationField) {
            $local = $definition::getFields()->getByStorageName(
                $association->getMappingLocalColumn()
            );

            $reference = $definition::getFields()->getByStorageName(
                $association->getMappingReferenceColumn()
            );

            $mapping = [
                $local->getPropertyName() => $parent['value'],
                $reference->getPropertyName() => $id,
            ];

            $deleteResult = $this->entityWriter->delete(
                $definition,
                [$mapping],
                WriteContext::createFromContext($context)
            );

            if (empty($deleteResult->getDeleted())) {
                throw new ResourceNotFoundException($definition::getEntityName(), $mapping);
            }

            return $this->responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        throw new \RuntimeException(sprintf('Unsupported association for field %s', $association->getPropertyName()));
    }

    private function fetchListing(Request $request, Context $context, string $path): EntitySearchResult
    {
        $pathSegments = $this->buildEntityPath($path);

        $first = array_shift($pathSegments);

        /** @var EntityDefinition|string $definition */
        $definition = $first['definition'];

        if (!$definition) {
            throw new NotFoundHttpException('The requested entity does not exist.');
        }

        $repository = $this->getRepository($definition, $request);

        $criteria = new Criteria();

        if (empty($pathSegments)) {
            $criteria = $this->searchCriteriaBuilder->handleRequest($request, $criteria, $definition, $context);

            return $repository->search($criteria, $context);
        }

        $child = array_pop($pathSegments);
        $parent = $first;

        if (!empty($pathSegments)) {
            $parent = array_pop($pathSegments);
        }

        $association = $child['field'];

        $parentDefinition = $parent['definition'];

        $definition = $child['definition'];
        if ($association instanceof ManyToManyAssociationField) {
            $definition = $association->getReferenceDefinition();
        }

        $criteria = $this->searchCriteriaBuilder->handleRequest($request, $criteria, $definition, $context);

        if ($association instanceof ManyToManyAssociationField) {
            //fetch inverse association definition for filter
            $reverse = $definition::getFields()->filter(
                function (Field $field) use ($association) {
                    return $field instanceof ManyToManyAssociationField && $association->getMappingDefinition() === $field->getMappingDefinition();
                }
            );

            //contains now the inverse side association: category.products
            $reverse = $reverse->first();

            /* @var ManyToManyAssociationField $reverse */
            $criteria->addFilter(
                new TermQuery(
                    sprintf('%s.%s.id', $definition::getEntityName(), $reverse->getPropertyName()),
                    $parent['value']
                )
            );
        } elseif ($association instanceof OneToManyAssociationField) {
            /*
             * Example
             * Route:           /api/product/SW1/prices
             * $definition:     \Shopware\Core\Content\Product\Definition\ProductPriceDefinition
             */

            //get foreign key definition of reference
            $foreignKey = $definition::getFields()->getByStorageName(
                $association->getReferenceField()
            );

            $criteria->addFilter(
                new TermQuery(
                //add filter to parent value: prices.productId = SW1
                    $definition::getEntityName() . '.' . $foreignKey->getPropertyName(),
                    $parent['value']
                )
            );
        } elseif ($association instanceof ManyToOneAssociationField) {
            /*
             * Example
             * Route:           /api/product/SW1/manufacturer
             * $definition:     \Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition
             */

            //get inverse association to filter to parent value
            $reverse = $definition::getFields()->filter(
                function (Field $field) use ($parentDefinition) {
                    return $field instanceof OneToManyAssociationField && $parentDefinition === $field->getReferenceClass();
                }
            );
            $reverse = $reverse->first();

            /* @var OneToManyAssociationField $reverse */
            $criteria->addFilter(
                new TermQuery(
                //filter inverse association to parent value:  manufacturer.products.id = SW1
                    sprintf('%s.%s.id', $definition::getEntityName(), $reverse->getPropertyName()),
                    $parent['value']
                )
            );
        }

        $repository = $this->getRepository($definition, $request);

        return $repository->search($criteria, $context);
    }

    private function getDefinitionOfPath(string $path): string
    {
        $pathSegments = $this->buildEntityPath($path);

        $first = array_shift($pathSegments);

        /** @var EntityDefinition|string $definition */
        $definition = $first['definition'];

        if (empty($pathSegments)) {
            return $definition;
        }

        $child = array_pop($pathSegments);

        $association = $child['field'];

        if ($association instanceof ManyToManyAssociationField) {
            /*
             * Example:
             * route:           /api/product/SW1/categories
             * $definition:     \Shopware\Core\Content\Category\CategoryDefinition
             */
            return $association->getReferenceDefinition();
        }

        return $child['definition'];
    }

    private function write(Request $request, Context $context, string $path, string $type): Response
    {
        $payload = $this->getRequestBody($request);
        $noContent = !$request->query->has('_response');
        $appendLocationHeader = $type === self::WRITE_CREATE;

        if ($this->isCollection($payload)) {
            throw new BadRequestHttpException('Only single write operations are supported. Please send the entities one by one or use the /sync api endpoint.');
        }

        $pathSegments = $this->buildEntityPath($path);

        $last = $pathSegments[\count($pathSegments) - 1];

        if ($type === self::WRITE_CREATE && !empty($last['value'])) {
            $methods = ['GET', 'PATCH', 'DELETE'];
            throw new MethodNotAllowedHttpException($methods, sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), implode(', ', $methods)));
        }

        if ($type === self::WRITE_UPDATE && isset($last['value'])) {
            $payload['id'] = $last['value'];
        }

        $first = array_shift($pathSegments);

        /* @var string|EntityDefinition $definition */
        if (\count($pathSegments) === 0) {
            $definition = $first['definition'];

            $repository = $this->getRepository($definition, $request);

            $events = $this->executeWriteOperation($repository, $payload, $context, $type);
            $event = $events->getEventByDefinition($definition);
            $eventIds = $event->getIds();
            $entityId = array_shift($eventIds);

            if ($noContent) {
                return $this->responseFactory->createRedirectResponse($definition, $entityId, $request, $context);
            }

            $entities = $repository->read(new ReadCriteria($event->getIds()), $context);

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $request, $context, $appendLocationHeader);
        }

        $child = array_pop($pathSegments);

        $parent = $first;
        if (!empty($pathSegments)) {
            $parent = array_pop($pathSegments);
        }

        $definition = $child['definition'];

        $association = $child['field'];

        /** @var EntityDefinition|string $parentDefinition */
        $parentDefinition = $parent['definition'];

        /* @var Entity $entity */
        if ($association instanceof OneToManyAssociationField) {
            $foreignKey = $definition::getFields()
                ->getByStorageName($association->getReferenceField());

            $payload[$foreignKey->getPropertyName()] = $parent['value'];

            $repository = $this->getRepository($definition, $request);
            $events = $this->executeWriteOperation($repository, $payload, $context, $type);

            if ($noContent) {
                return $this->responseFactory->createRedirectResponse($definition, $parent['value'], $request, $context);
            }

            $event = $events->getEventByDefinition($definition);

            $repository = $this->getRepository($definition, $request);

            $entities = $repository->read(new ReadCriteria($event->getIds()), $context);

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $request, $context, $appendLocationHeader);
        }

        if ($association instanceof ManyToOneAssociationField) {
            $repository = $this->getRepository($definition, $request);
            $events = $this->executeWriteOperation($repository, $payload, $context, $type);
            $event = $events->getEventByDefinition($definition);

            $entityIds = $event->getIds();
            $entityId = array_pop($entityIds);

            $foreignKey = $parentDefinition::getFields()->getByStorageName($association->getStorageName());

            $payload = [
                'id' => $parent['value'],
                $foreignKey->getPropertyName() => $entityId,
            ];

            $repository = $this->getRepository($parentDefinition, $request);
            $repository->update([$payload], $context);

            if ($noContent) {
                return $this->responseFactory->createRedirectResponse($definition, $entityId, $request, $context);
            }

            $entities = $repository->read(new ReadCriteria($event->getIds()), $context);

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $request, $context, $appendLocationHeader);
        }

        /** @var ManyToManyAssociationField $association */

        /** @var EntityDefinition|string $reference */
        $reference = $association->getReferenceDefinition();

        $repository = $this->getRepository($reference, $request);
        $events = $this->executeWriteOperation($repository, $payload, $context, $type);
        $event = $events->getEventByDefinition($reference);

        $repository = $this->getRepository($reference, $request);

        $entities = $repository->read(new ReadCriteria($event->getIds()), $context);

        $entity = $entities->first();

        $repository = $this->getRepository($parentDefinition, $request);

        $payload = [
            'id' => $parent['value'],
            $association->getPropertyName() => [
                ['id' => $entity->getId()],
            ],
        ];

        $repository->update([$payload], $context);

        if ($noContent) {
            return $this->responseFactory->createRedirectResponse($reference, $entity->getId(), $request, $context);
        }

        return $this->responseFactory->createDetailResponse($entity, $definition, $request, $context, $appendLocationHeader);
    }

    /**
     * @param RepositoryInterface $repository
     * @param array               $payload
     * @param Context             $context
     * @param string              $type
     *
     * @return EntityWrittenContainerEvent
     */
    private function executeWriteOperation(RepositoryInterface $repository, array $payload, Context $context, string $type): EntityWrittenContainerEvent
    {
        if ($type === self::WRITE_CREATE) {
            return $repository->create([$payload], $context);
        }

        if ($type === self::WRITE_UPDATE) {
            return $repository->update([$payload], $context);
        }

        throw new \RuntimeException('Unsupported write operation.');
    }

    private function getAssociation(FieldCollection $fields, array $keys): AssociationInterface
    {
        $key = array_shift($keys);

        /** @var AssociationInterface $field */
        $field = $fields->get($key);

        if (empty($keys)) {
            return $field;
        }

        /** @var string|EntityDefinition $reference */
        $reference = $field->getReferenceClass();

        $nested = $reference::getFields();

        return $this->getAssociation($nested, $keys);
    }

    private function buildEntityPath(string $pathInfo): array
    {
        $exploded = explode('/', $pathInfo);

        $parts = [];
        foreach ($exploded as $index => $part) {
            if ($index % 2) {
                continue;
            }
            if (empty($part)) {
                continue;
            }
            $value = null;
            if (isset($exploded[$index + 1])) {
                $value = $exploded[$index + 1];
            }

            if (empty($parts)) {
                $part = $this->urlToSnakeCase($part);
            } else {
                $part = $this->urlToCamelCase($part);
            }

            $parts[] = [
                'entity' => $part,
                'value' => $value,
            ];
        }

        $parts = array_filter($parts);
        $first = array_shift($parts);

        try {
            $root = $this->definitionRegistry->get($first['entity']);
        } catch (DefinitionNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $entities = [
            [
                'entity' => $first['entity'],
                'value' => $first['value'],
                'definition' => $root,
                'field' => null,
            ],
        ];

        foreach ($parts as $part) {
            $fields = $root::getFields();

            /** @var AssociationInterface $field */
            $field = $fields->get($part['entity']);
            if (!$field) {
                $path = implode('.', array_column($entities, 'entity')) . '.' . $part['entity'];
                throw new NotFoundHttpException(sprintf('Resource at path "%s" is not an existing relation.', $path));
            }

            if ($field instanceof ManyToManyAssociationField) {
                $root = $field->getReferenceDefinition();
            } else {
                $root = $field->getReferenceClass();
            }

            $entities[] = [
                'entity' => $part['entity'],
                'value' => $part['value'],
                'definition' => $field->getReferenceClass(),
                'field' => $field,
            ];
        }

        return $entities;
    }

    private function urlToSnakeCase(string $name): string
    {
        return str_replace('-', '_', $name);
    }

    private function urlToCamelCase(string $name): string
    {
        $parts = explode('-', $name);
        $parts = array_map('ucfirst', $parts);

        return lcfirst(implode('', $parts));
    }

    /**
     * Return a nested array structure of based on the content-type
     *
     * @param Request $request
     *
     * @return array
     */
    private function getRequestBody(Request $request): array
    {
        $contentType = $request->headers->get('CONTENT_TYPE', '');
        $semicolonPosition = strpos($contentType, ';');

        if ($semicolonPosition !== false) {
            $contentType = substr($contentType, 0, $semicolonPosition);
        }

        try {
            switch ($contentType) {
                case 'application/vnd.api+json':
                    return $this->serializer->decode($request->getContent(), 'jsonapi');
                case 'application/json':
                    return $this->serializer->decode($request->getContent(), 'json');
            }
        } catch (InvalidArgumentException | UnexpectedValueException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        throw new UnsupportedMediaTypeHttpException(sprintf('The Content-Type "%s" is unsupported.', $contentType));
    }

    private function isCollection(array $array): bool
    {
        return array_keys($array) === range(0, \count($array) - 1);
    }

    /**
     * @param Request                 $request
     * @param Context                 $context
     * @param string|EntityDefinition $definition
     * @param string                  $id
     *
     * @throws ResourceNotFoundException
     */
    private function doDelete(Request $request, Context $context, string $definition, string $id): void
    {
        try {
            $repository = $this->getRepository($definition, $request);
        } catch (UnknownRepositoryVersionException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $payload = [];
        $fields = $definition::getPrimaryKeys()->filter(function (Field $field) {
            return !$field instanceof VersionField && !$field instanceof ReferenceVersionField && !$field instanceof TenantIdField;
        });

        try {
            $payload = $this->getRequestBody($request);
        } catch (\Exception $exception) {
            // empty payload is allowed for DELETE requests
        }

        if ($fields->count() > 1) {
            $mapping = $payload;
        } else {
            $pk = $fields->first();
            /** @var Field $pk */
            $mapping = [$pk->getPropertyName() => $id];
        }

        $deleteEvent = $repository->delete([$mapping], $context);

        if (empty($deleteEvent->getErrors())) {
            return;
        }

        throw new ResourceNotFoundException($definition::getEntityName(), $mapping);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param Request                 $request
     *
     * @throws UnknownRepositoryVersionException
     *
     * @return RepositoryInterface
     */
    private function getRepository(string $definition, Request $request): RepositoryInterface
    {
        $repositoryClass = $definition::getEntityName() . '.repository';

        if ($this->has($repositoryClass) === false) {
            throw new UnknownRepositoryVersionException($definition::getEntityName(), (int) $request->get('version'));
        }

        return $this->get($definition::getEntityName() . '.repository');
    }

    private function hasScope(Request $request, string $scopeIdentifier): bool
    {
        $scopes = array_flip($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES));

        return isset($scopes[$scopeIdentifier]);
    }
}
