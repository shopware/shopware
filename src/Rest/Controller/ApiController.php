<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Shopware\Api\Entity\DefinitionRegistry;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Parser\QueryStringParser;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\FieldException\WriteStackException;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Rest\Exception\ResourceNotFoundException;
use Shopware\Rest\Exception\WriteStackHttpException;
use Shopware\Rest\Response\ResponseFactory;
use Shopware\Rest\RestContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function __construct(DefinitionRegistry $definitionRegistry, Serializer $serializer, ResponseFactory $responseFactory, EntityWriterInterface $entityWriter)
    {
        $this->definitionRegistry = $definitionRegistry;
        $this->serializer = $serializer;
        $this->responseFactory = $responseFactory;
        $this->entityWriter = $entityWriter;
    }

    public function detailAction(Request $request, RestContext $context): Response
    {
        $path = $this->buildEntityPath($request->getPathInfo());

        $root = $path[0]['entity'];
        $id = $path[\count($path) - 1]['value'];

        $definition = $this->definitionRegistry->get($root);

        $associations = array_column($path, 'entity');
        array_shift($associations);

        if (empty($associations)) {
            $repository = $this->get($definition::getRepositoryClass());
        } else {
            /** @var EntityDefinition $definition */
            $field = $this->getAssociation($definition::getFields(), $associations);

            $definition = $field->getReferenceClass();
            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getReferenceDefinition();
            }

            $repository = $this->get($definition::getRepositoryClass());
        }

        /** @var RepositoryInterface $repository */
        $entities = $repository->readDetail([$id], $context->getShopContext());

        $entity = $entities->get($id);

        if ($entity === null) {
            throw new ResourceNotFoundException($definition::getEntityName(), $id);
        }

        return $this->responseFactory->createDetailResponse($entity, (string) $definition, $context);
    }

    public function listAction(Request $request, RestContext $context): Response
    {
        $path = $this->buildEntityPath($request->getPathInfo());

        $first = array_shift($path);

        /** @var EntityDefinition $definition */
        $definition = $first['definition'];

        /** @var RepositoryInterface $repository */
        $repository = $this->get($definition::getRepositoryClass());

        if (empty($path)) {
            $data = $repository->search(
                $this->createListingCriteria($request),
                $context->getShopContext()
            );

            return $this->responseFactory->createListingResponse($data, (string) $definition, $context);
        }

        $child = array_pop($path);
        $parent = $first;

        if (!empty($path)) {
            $parent = array_pop($path);
        }

        $criteria = $this->createListingCriteria($request);

        $association = $child['field'];

        $parentDefinition = $parent['definition'];

        $definition = $child['definition'];

        if ($association instanceof ManyToManyAssociationField) {
            /*
             * Example:
             * route:           /api/product/SW1/categories
             * $definition:     \Shopware\Category\Definition\CategoryDefinition
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
             * $definition:     \Shopware\Product\Definition\ProductPriceDefinition
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
             * $definition:     \Shopware\Product\Definition\ProductManufacturerDefinition
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

        /** @var RepositoryInterface $repository */
        $repository = $this->get($definition::getRepositoryClass());

        $result = $repository->search($criteria, $context->getShopContext());

        return $this->responseFactory->createListingResponse($result, (string) $definition, $context);
    }

    public function createAction(Request $request, RestContext $context): Response
    {
        return $this->write($request, $context, self::WRITE_CREATE);
    }

    public function updateAction(Request $request, RestContext $context)
    {
        return $this->write($request, $context, self::WRITE_UPDATE);
    }

    public function deleteAction(Request $request, RestContext $context): Response
    {
        $path = $this->buildEntityPath($request->getPathInfo());

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
                WriteContext::createFromShopContext($context->getShopContext())
            );

            return $this->responseFactory->createRedirectResponse($definition, $id, $context);
        }

        throw new \RuntimeException(sprintf('Unsupported association for field %s', $association->getPropertyName()));
    }

    private function write(Request $request, RestContext $context, string $type): Response
    {
        $payload = $this->getRequestBody($request);
        $responseDataType = $this->getResponseDataType($request);
        $appendLocationHeader = $type === self::WRITE_CREATE;

        if ($this->isCollection($payload)) {
            throw new BadRequestHttpException('Only single write operations are supported. Please send the entities one by one or use the /sync api endpoint.');
        }

        $path = $this->buildEntityPath($request->getPathInfo());

        $last = $path[\count($path) - 1];

        if ($type === self::WRITE_UPDATE && isset($last['value'])) {
            $payload['id'] = $last['value'];
        }

        $first = array_shift($path);

        /* @var string|EntityDefinition $definition */
        if (\count($path) === 0) {
            $definition = $first['definition'];

            /** @var RepositoryInterface $repository */
            $repository = $this->get($definition::getRepositoryClass());

            $events = $this->executeWriteOperation($definition, $payload, $context, $type);

            if (!$responseDataType) {
                return $this->responseFactory->createRedirectResponse($definition, $payload['id'], $context);
            }

            $event = $events->getEventByDefinition($definition);

            $entities = $repository->readBasic($event->getIds(), $context->getShopContext());

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

            $repository = $this->get($definition::getRepositoryClass());
            $entities = $repository->readBasic($event->getIds(), $context->getShopContext());

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

            $repository = $this->get($parentDefinition::getRepositoryClass());
            $repository->update([$payload], $context->getShopContext());

            if (!$responseDataType) {
                return $this->responseFactory->createRedirectResponse($definition, $entityId, $context);
            }

            $entities = $repository->readBasic($event->getIds(), $context->getShopContext());

            return $this->responseFactory->createDetailResponse($entities->first(), $definition, $context, $appendLocationHeader);
        }

        /** @var ManyToManyAssociationField $association */

        /** @var EntityDefinition|string $reference */
        $reference = $association->getReferenceDefinition();

        $events = $this->executeWriteOperation($reference, $payload, $context, $type);
        $event = $events->getEventByDefinition($reference);

        $repository = $this->get($reference::getRepositoryClass());
        $entities = $repository->readBasic($event->getIds(), $context->getShopContext());
        $entity = $entities->first();

        $repository = $this->get($parentDefinition::getRepositoryClass());

        $foreignKey = $definition::getFields()->getByStorageName(
            $association->getMappingReferenceColumn()
        );

        $payload = [
            'id' => $parent['value'],
            $association->getPropertyName() => [
                ['id' => $entity->getId()],
            ],
        ];

        $repository->update([$payload], $context->getShopContext());

        if (!$responseDataType) {
            return $this->responseFactory->createRedirectResponse($reference, $entity->getId(), $context);
        }

        return $this->responseFactory->createDetailResponse($entity, $definition, $context, $appendLocationHeader);
    }

    private function executeWriteOperation(string $definition, array $payload, RestContext $context, string $type): GenericWrittenEvent
    {
        /** @var EntityDefinition $definition */
        $repository = $this->get($definition::getRepositoryClass());

        try {
            /* @var RepositoryInterface $repository */
            switch ($type) {
                case self::WRITE_CREATE:

                    return $repository->create([$payload], $context->getShopContext());

                case self::WRITE_UPDATE:
                default:
                    return $repository->update([$payload], $context->getShopContext());
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
        $exploded = str_replace('/api/', '', $pathInfo);
        $exploded = explode('/', $exploded);

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

    private function createListingCriteria(Request $request): Criteria
    {
        $criteria = new Criteria();
        $criteria->setFetchCount(true);
        $criteria->setLimit(10);

        if ($request->query->has('offset')) {
            $criteria->setOffset((int) $request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->setLimit((int) $request->query->get('limit'));
        }

        if ($request->query->has('query')) {
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $pageQuery = $request->query->get('page', []);

        if (array_key_exists('offset', $pageQuery)) {
            $criteria->setOffset((int) $pageQuery['offset']);
        }

        if (array_key_exists('limit', $pageQuery)) {
            $criteria->setLimit((int) $pageQuery['limit']);
        }

        return $criteria;
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
        $contentType = $request->headers->get('CONTENT_TYPE');
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
        /** @var RepositoryInterface $repository */
        $repository = $this->get($definition::getRepositoryClass());

        $payload = [];
        $fields = $definition::getPrimaryKeys()->filter(function (Field $field) {
            return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
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

        $repository->delete([$mapping], $context->getShopContext());
    }

    private function getResponseDataType(Request $request): ?string
    {
        if ($request->query->has('_response') === false) {
            return null;
        }

        $responses = ['basic', 'detail'];
        $response = $request->query->get('_response');

        if (!\in_array($response, $responses, true)) {
            throw new BadRequestHttpException(sprintf('The response type "%s" is not supported. Available types are: %s', $response, implode(', ', $responses)));
        }

        return $response;
    }
}
