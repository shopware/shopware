<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Controller;

use Shopware\Framework\Api\Context\RestContext;
use Shopware\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Framework\Api\Exception\UnknownRepositoryVersionException;
use Shopware\Framework\Api\Exception\WriteStackHttpException;
use Shopware\Framework\Api\Response\ResponseFactory;
use Shopware\Framework\ORM\DefinitionRegistry;
use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\AssociationInterface;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\SearchCriteriaBuilder;
use Shopware\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Serializer;

class ApiController extends Controller
{
    public const WRITE_UPDATE = 'update';
    public const WRITE_CREATE = 'create';

    public const RESPONSE_BASIC = 'basic';
    public const RESPONSE_DETAIL = 'detail';

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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        DefinitionRegistry $definitionRegistry,
        Serializer $serializer,
        ResponseFactory $responseFactory,
        EntityWriterInterface $entityWriter,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->serializer = $serializer;
        $this->responseFactory = $responseFactory;
        $this->entityWriter = $entityWriter;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function detail(Request $request, RestContext $context, string $path): Response
    {
        $path = $this->buildEntityPath($path);

        $root = $path[0]['entity'];
        $id = $path[\count($path) - 1]['value'];

        $definition = $this->definitionRegistry->get($root);

        $associations = array_column($path, 'entity');
        array_shift($associations);

        if (empty($associations)) {
            $repository = $this->getRepository($definition, $context->getVersion());
        } else {
            /** @var EntityDefinition $definition */
            $field = $this->getAssociation($definition::getFields(), $associations);

            $definition = $field->getReferenceClass();
            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getReferenceDefinition();
            }

            $repository = $this->getRepository($definition, $context->getVersion());
        }

        /** @var RepositoryInterface $repository */
        $entities = $repository->readDetail([$id], $context->getContext());

        $entity = $entities->get($id);

        if ($entity === null) {
            throw new ResourceNotFoundException($definition::getEntityName(), $id);
        }

        return $this->responseFactory->createDetailResponse($entity, (string) $definition, $context);
    }

    public function list(Request $request, RestContext $context, string $path): Response
    {
        $path = $this->buildEntityPath($path);

        $first = array_shift($path);

        /** @var EntityDefinition|string $definition */
        $definition = $first['definition'];

        if (!$definition) {
            throw new NotFoundHttpException('The requested entity does not exist.');
        }

        $repository = $this->getRepository($definition, $context->getVersion());

        $criteria = new Criteria();

        if (empty($path)) {
            $criteria = $this->searchCriteriaBuilder->handleRequest(
                $request,
                $criteria,
                $definition,
                $context->getContext()
            );

            $data = $repository->search($criteria, $context->getApplicationContext());

            return $this->responseFactory->createListingResponse($data, (string) $definition, $context);
        }

        $child = array_pop($path);
        $parent = $first;

        if (!empty($path)) {
            $parent = array_pop($path);
        }

        $criteria = $this->searchCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $definition,
            $context->getContext()
        );

        $association = $child['field'];

        $parentDefinition = $parent['definition'];

        $definition = $child['definition'];

        if ($association instanceof ManyToManyAssociationField) {
            /*
             * Example:
             * route:           /api/product/SW1/categories
             * $definition:     \Shopware\Content\Category\CategoryDefinition
             */
            $definition = $association->getReferenceDefinition();

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
             * $definition:     \Shopware\Content\Product\Definition\ProductPriceDefinition
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
             * $definition:     \Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition
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

        $repository = $this->getRepository($definition, $context->getVersion());

        $result = $repository->search($criteria, $context->getContext());

        return $this->responseFactory->createListingResponse($result, (string) $definition, $context);
    }

    public function create(Request $request, RestContext $context, string $path): Response
    {
        return $this->write($request, $context, $path, self::WRITE_CREATE);
    }

    public function update(Request $request, RestContext $context, string $path)
    {
        return $this->write($request, $context, $path, self::WRITE_UPDATE);
    }

    public function delete(Request $request, RestContext $context, string $path): Response
    {
        $path = $this->buildEntityPath($path);

        $last = $path[\count($path) - 1];

        $id = $last['value'];

        $first = array_shift($path);

        /* @var string|EntityDefinition $definition */
        if (\count($path) === 0) {
            //first api level call /product/{id}
            $definition = $first['definition'];

            $this->doDelete($context, $definition, $id);

            return $this->responseFactory->createRedirectResponse($definition, $id, $context);
        }

        $child = array_pop($path);
        $parent = $first;
        if (!empty($path)) {
            $parent = array_pop($path);
        }

        $definition = $child['definition'];

        /** @var AssociationInterface $association */
        $association = $child['field'];

        if ($association instanceof OneToManyAssociationField) {
            $this->doDelete($context, $definition, $id);

            return $this->responseFactory->createRedirectResponse($definition, $id, $context);
        }

        // DELETE api/product/{id}/manufacturer/{id}
        if ($association instanceof ManyToOneAssociationField) {
            $this->doDelete($context, $definition, $id);

            return $this->responseFactory->createRedirectResponse($definition, $id, $context);
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

            $this->entityWriter->delete(
                $definition,
                [$mapping],
                WriteContext::createFromContext($context->getContext())
            );

            return $this->responseFactory->createRedirectResponse($definition, $id, $context);
        }

        throw new \RuntimeException(sprintf('Unsupported association for field %s', $association->getPropertyName()));
    }

    public function search(Request $request, RestContext $context, string $path): Response
    {
        $path = $this->buildEntityPath($path);
        $first = array_shift($path);

        /** @var EntityDefinition|string $definition */
        $definition = $first['definition'];

        if (!$definition) {
            throw new NotFoundHttpException('The requested entity does not exist.');
        }

        $repository = $this->getRepository($definition, $context->getVersion());

        if (!empty($path)) {
            throw new \RuntimeException('Only entities are supported');
        }

        $criteria = new Criteria();

        $data = $repository->search(
            $this->searchCriteriaBuilder->handleRequest(
                $request,
                $criteria,
                $definition,
                $context->getContext()
            ),
            $context->getContext()
        );

        return $this->responseFactory->createListingResponse($data, (string) $definition, $context);
    }

    private function write(Request $request, RestContext $context, string $path, string $type): Response
    {
        $payload = $this->getRequestBody($request);
        $responseDataType = $this->getResponseDataType($request);
        $appendLocationHeader = $type === self::WRITE_CREATE;

        if ($this->isCollection($payload)) {
            throw new BadRequestHttpException('Only single write operations are supported. Please send the entities one by one or use the /sync api endpoint.');
        }

        $path = $this->buildEntityPath($path);

        $last = $path[\count($path) - 1];

        if ($type === self::WRITE_UPDATE && isset($last['value'])) {
            $payload['id'] = $last['value'];
        }

        $first = array_shift($path);

        /* @var string|EntityDefinition $definition */
        if (\count($path) === 0) {
            $definition = $first['definition'];

            $repository = $this->getRepository($definition, $context->getVersion());

            $events = $this->executeWriteOperation($definition, $payload, $context, $type);
            $event = $events->getEventByDefinition($definition);
            $eventIds = $event->getIds();
            $entityId = array_shift($eventIds);

            if (!$responseDataType) {
                return $this->responseFactory->createRedirectResponse($definition, $entityId, $context);
            }

            if ($responseDataType === self::RESPONSE_DETAIL) {
                $entities = $repository->readDetail($event->getIds(), $context->getContext());
            } else {
                $entities = $repository->readBasic($event->getIds(), $context->getContext());
            }

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $context, $appendLocationHeader);
        }

        $child = array_pop($path);

        $parent = $first;
        if (!empty($path)) {
            $parent = array_pop($path);
        }

        $definition = $child['definition'];

        $association = $child['field'];

        /** @var EntityDefinition $parentDefinition */
        $parentDefinition = $parent['definition'];

        /* @var RepositoryInterface $repository */

        /* @var Entity $entity */
        if ($association instanceof OneToManyAssociationField) {
            $foreignKey = $definition::getFields()
                ->getByStorageName($association->getReferenceField());

            $payload[$foreignKey->getPropertyName()] = $parent['value'];

            $events = $this->executeWriteOperation($definition, $payload, $context, $type);

            if (!$responseDataType) {
                return $this->responseFactory->createRedirectResponse($definition, $parent['value'], $context);
            }

            $event = $events->getEventByDefinition($definition);

            $repository = $this->getRepository($definition, $context->getVersion());

            if ($responseDataType === self::RESPONSE_DETAIL) {
                $entities = $repository->readDetail($event->getIds(), $context->getContext());
            } else {
                $entities = $repository->readBasic($event->getIds(), $context->getContext());
            }

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $context, $appendLocationHeader);
        }

        if ($association instanceof ManyToOneAssociationField) {
            $events = $this->executeWriteOperation($definition, $payload, $context, $type);
            $event = $events->getEventByDefinition($definition);

            $entityIds = $event->getIds();
            $entityId = array_pop($entityIds);

            $foreignKey = $parentDefinition::getFields()->getByStorageName($association->getStorageName());

            $payload = [
                'id' => $parent['value'],
                $foreignKey->getPropertyName() => $entityId,
            ];

            $repository = $this->getRepository($parentDefinition, $context->getVersion());
            $repository->update([$payload], $context->getContext());

            if (!$responseDataType) {
                return $this->responseFactory->createRedirectResponse($definition, $entityId, $context);
            }

            if ($responseDataType === self::RESPONSE_DETAIL) {
                $entities = $repository->readDetail($event->getIds(), $context->getContext());
            } else {
                $entities = $repository->readBasic($event->getIds(), $context->getContext());
            }

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $context, $appendLocationHeader);
        }

        /** @var ManyToManyAssociationField $association */

        /** @var EntityDefinition|string $reference */
        $reference = $association->getReferenceDefinition();

        $events = $this->executeWriteOperation($reference, $payload, $context, $type);
        $event = $events->getEventByDefinition($reference);

        $repository = $this->getRepository($reference, $context->getVersion());

        if ($responseDataType === self::RESPONSE_DETAIL) {
            $entities = $repository->readDetail($event->getIds(), $context->getContext());
        } else {
            $entities = $repository->readBasic($event->getIds(), $context->getContext());
        }

        $entity = $entities->first();

        $repository = $this->getRepository($parentDefinition, $context->getVersion());

        $payload = [
            'id' => $parent['value'],
            $association->getPropertyName() => [
                ['id' => $entity->getId()],
            ],
        ];

        $repository->update([$payload], $context->getContext());

        if (!$responseDataType) {
            return $this->responseFactory->createRedirectResponse($reference, $entity->getId(), $context);
        }

        return $this->responseFactory->createDetailResponse($entity, $definition, $context, $appendLocationHeader);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param array                   $payload
     * @param RestContext             $context
     * @param string                  $type
     *
     * @return GenericWrittenEvent
     */
    private function executeWriteOperation(string $definition, array $payload, RestContext $context, string $type): GenericWrittenEvent
    {
        $repository = $this->getRepository($definition, $context->getVersion());

        try {
            /* @var RepositoryInterface $repository */
            switch ($type) {
                case self::WRITE_CREATE:

                    return $repository->create([$payload], $context->getContext());

                case self::WRITE_UPDATE:
                default:
                    return $repository->update([$payload], $context->getContext());
            }
        } catch (WriteStackException $exceptionStack) {
            throw new WriteStackHttpException($exceptionStack);
        }
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

        $root = $this->definitionRegistry->get($first['entity']);

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
                throw new \InvalidArgumentException(
                    sprintf('')
                );
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
        } catch (\Exception $exception) {
            throw new HttpException(500, $exception->getMessage());
        }

        throw new UnsupportedMediaTypeHttpException(sprintf('The Content-Type "%s" is unsupported.', $contentType));
    }

    private function isCollection(array $array): bool
    {
        return array_keys($array) === range(0, \count($array) - 1);
    }

    /**
     * @param RestContext             $context
     * @param string|EntityDefinition $definition
     * @param string                  $id
     *
     * @throws \RuntimeException
     */
    private function doDelete(RestContext $context, $definition, $id): void
    {
        $repository = $this->getRepository($definition, $context->getVersion());

        $payload = [];
        $fields = $definition::getPrimaryKeys()->filter(function (Field $field) {
            return !$field instanceof VersionField && !$field instanceof ReferenceVersionField && !$field instanceof TenantIdField;
        });

        try {
            $payload = $this->getRequestBody($context->getRequest());
        } catch (\Exception $exception) {
            // empty payload is allowed for DELETE requests
        }

        if ($fields->count() > 1 && empty($payload)) {
            throw new \RuntimeException(
                sprintf('Entity primary key is defined by multiple columns. Please provide primary key in payload.')
            );
        }

        if ($fields->count() > 1) {
            $mapping = $payload;
        } else {
            $pk = $fields->first();
            /** @var Field $pk */
            $mapping = [$pk->getPropertyName() => $id];
        }

        $repository->delete([$mapping], $context->getContext());
    }

    private function getResponseDataType(Request $request): ?string
    {
        if ($request->query->has('_response') === false) {
            return null;
        }

        $responses = [self::RESPONSE_BASIC, self::RESPONSE_DETAIL];
        $response = $request->query->get('_response');

        if (!\in_array($response, $responses, true)) {
            throw new BadRequestHttpException(sprintf('The response type "%s" is not supported. Available types are: %s', $response, implode(', ', $responses)));
        }

        return $response;
    }

    /**
     * @param string|EntityDefinition $definition
     * @param int                     $version
     *
     * @throws UnknownRepositoryVersionException
     *
     * @return RepositoryInterface
     */
    private function getRepository(string $definition, int $version): RepositoryInterface
    {
        $repositoryClass = sprintf('%s.v%d', $definition::getRepositoryClass(), $version);

        if ($this->has($repositoryClass) === false) {
            throw new UnknownRepositoryVersionException($definition::getEntityName(), $version);
        }

        return $this->get($repositoryClass);
    }
}
