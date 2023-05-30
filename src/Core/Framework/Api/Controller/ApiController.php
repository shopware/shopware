<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Api\Exception\InvalidVersionNameException;
use Shopware\Core\Framework\Api\Exception\LiveVersionDeleteException;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Exception\NoEntityClonedException;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingReverseAssociation;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * @phpstan-type EntityPathSegment array{entity: string, value: ?string, definition: EntityDefinition, field: ?Field}
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class ApiController extends AbstractController
{
    final public const WRITE_UPDATE = 'update';
    final public const WRITE_CREATE = 'create';
    final public const WRITE_DELETE = 'delete';

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly DecoderInterface $serializer,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly ApiVersionConverter $apiVersionConverter,
        private readonly EntityProtectionValidator $entityProtectionValidator,
        private readonly AclCriteriaValidator $criteriaValidator
    ) {
    }

    #[Route(path: '/api/_action/clone/{entity}/{id}', name: 'api.clone', methods: ['POST'], requirements: ['version' => '\d+', 'entity' => '[a-zA-Z-]+', 'id' => '[0-9a-f]{32}'])]
    public function clone(Context $context, string $entity, string $id, Request $request): JsonResponse
    {
        $behavior = new CloneBehavior(
            $request->request->all('overwrites'),
            $request->request->getBoolean('cloneChildren', true)
        );

        $entity = $this->urlToSnakeCase($entity);

        $definition = $this->definitionRegistry->getByEntityName($entity);
        $missing = $this->validateAclPermissions($context, $definition, AclRoleDefinition::PRIVILEGE_CREATE);
        if ($missing) {
            throw new MissingPrivilegeException([$missing]);
        }

        $eventContainer = $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($definition, $id, $behavior): EntityWrittenContainerEvent {
            /** @var EntityRepository $entityRepo */
            $entityRepo = $this->definitionRegistry->getRepository($definition->getEntityName());

            return $entityRepo->clone($id, $context, null, $behavior);
        });

        $event = $eventContainer->getEventByEntityName($definition->getEntityName());
        if (!$event) {
            throw new NoEntityClonedException($entity, $id);
        }

        $ids = $event->getIds();
        $newId = array_shift($ids);

        return new JsonResponse(['id' => $newId]);
    }

    #[Route(path: '/api/_action/version/{entity}/{id}', name: 'api.createVersion', methods: ['POST'], requirements: ['version' => '\d+', 'entity' => '[a-zA-Z-]+', 'id' => '[0-9a-f]{32}'])]
    public function createVersion(Request $request, Context $context, string $entity, string $id): Response
    {
        $entity = $this->urlToSnakeCase($entity);

        $versionId = $request->request->has('versionId') ? (string) $request->request->get('versionId') : null;
        $versionName = $request->request->has('versionName') ? (string) $request->request->get('versionName') : null;

        if ($versionId !== null && !Uuid::isValid($versionId)) {
            throw new InvalidUuidException($versionId);
        }

        if ($versionName !== null && !ctype_alnum($versionName)) {
            throw new InvalidVersionNameException();
        }

        try {
            $entityDefinition = $this->definitionRegistry->getByEntityName($entity);
        } catch (DefinitionNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $versionId = $context->scope(Context::CRUD_API_SCOPE, fn (Context $context): string => $this->definitionRegistry->getRepository($entityDefinition->getEntityName())->createVersion($id, $context, $versionName, $versionId));

        return new JsonResponse([
            'versionId' => $versionId,
            'versionName' => $versionName,
            'id' => $id,
            'entity' => $entity,
        ]);
    }

    #[Route(path: '/api/_action/version/merge/{entity}/{versionId}', name: 'api.mergeVersion', methods: ['POST'], requirements: ['version' => '\d+', 'entity' => '[a-zA-Z-]+', 'versionId' => '[0-9a-f]{32}'])]
    public function mergeVersion(Context $context, string $entity, string $versionId): JsonResponse
    {
        $entity = $this->urlToSnakeCase($entity);

        if (!Uuid::isValid($versionId)) {
            throw new InvalidUuidException($versionId);
        }

        $entityDefinition = $this->getEntityDefinition($entity);
        $repository = $this->definitionRegistry->getRepository($entityDefinition->getEntityName());

        // change scope to be able to update write protected fields
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($repository, $versionId): void {
            $repository->merge($versionId, $context);
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/version/{versionId}/{entity}/{entityId}', name: 'api.deleteVersion', methods: ['POST'], requirements: ['version' => '\d+', 'entity' => '[a-zA-Z-]+', 'id' => '[0-9a-f]{32}'])]
    public function deleteVersion(Context $context, string $entity, string $entityId, string $versionId): JsonResponse
    {
        if ($versionId !== null && !Uuid::isValid($versionId)) {
            throw new InvalidUuidException($versionId);
        }

        if ($versionId === Defaults::LIVE_VERSION) {
            throw new LiveVersionDeleteException();
        }

        if ($entityId !== null && !Uuid::isValid($entityId)) {
            throw new InvalidUuidException($entityId);
        }

        try {
            $entityDefinition = $this->definitionRegistry->getByEntityName($this->urlToSnakeCase($entity));
        } catch (DefinitionNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $versionContext = $context->createWithVersionId($versionId);

        $entityRepository = $this->definitionRegistry->getRepository($entityDefinition->getEntityName());

        $versionContext->scope(Context::CRUD_API_SCOPE, function (Context $versionContext) use ($entityId, $entityRepository): void {
            $entityRepository->delete([['id' => $entityId]], $versionContext);
        });

        $versionRepository = $this->definitionRegistry->getRepository('version');
        $versionRepository->delete([['id' => $versionId]], $context);

        return new JsonResponse();
    }

    public function detail(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $pathSegments = $this->buildEntityPath($entityName, $path, $context);
        $permissions = $this->validatePathSegments($context, $pathSegments, AclRoleDefinition::PRIVILEGE_READ);

        $root = $pathSegments[0]['entity'];
        /** @var string $id id is always set, otherwise the route would not match */
        $id = $pathSegments[\count($pathSegments) - 1]['value'];

        $definition = $this->definitionRegistry->getByEntityName($root);

        $associations = array_column($pathSegments, 'entity');
        array_shift($associations);

        if (empty($associations)) {
            $repository = $this->definitionRegistry->getRepository($definition->getEntityName());
        } else {
            $field = $this->getAssociation($definition->getFields(), $associations);

            $definition = $field->getReferenceDefinition();
            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getToManyReferenceDefinition();
            }

            $repository = $this->definitionRegistry->getRepository($definition->getEntityName());
        }

        $criteria = new Criteria();
        $criteria = $this->criteriaBuilder->handleRequest($request, $criteria, $definition, $context);

        $criteria->setIds([$id]);

        // trigger acl validation
        $missing = $this->criteriaValidator->validate($definition->getEntityName(), $criteria, $context);
        $permissions = array_unique(array_filter(array_merge($permissions, $missing)));

        if (!empty($permissions)) {
            throw new MissingPrivilegeException($permissions);
        }

        $entity = $context->scope(Context::CRUD_API_SCOPE, fn (Context $context): ?Entity => $repository->search($criteria, $context)->get($id));

        if ($entity === null) {
            throw new ResourceNotFoundException($definition->getEntityName(), ['id' => $id]);
        }

        return $responseFactory->createDetailResponse($criteria, $entity, $definition, $request, $context);
    }

    public function searchIds(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        [$criteria, $repository] = $this->resolveSearch($request, $context, $entityName, $path);

        $result = $context->scope(Context::CRUD_API_SCOPE, fn (Context $context): IdSearchResult => $repository->searchIds($criteria, $context));

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => array_values($result->getIds()),
        ]);
    }

    public function search(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        [$criteria, $repository] = $this->resolveSearch($request, $context, $entityName, $path);

        $result = $context->scope(Context::CRUD_API_SCOPE, fn (Context $context): EntitySearchResult => $repository->search($criteria, $context));

        $definition = $this->getDefinitionOfPath($entityName, $path, $context);

        return $responseFactory->createListingResponse($criteria, $result, $definition, $request, $context);
    }

    public function list(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        [$criteria, $repository] = $this->resolveSearch($request, $context, $entityName, $path);

        $result = $context->scope(Context::CRUD_API_SCOPE, fn (Context $context): EntitySearchResult => $repository->search($criteria, $context));

        $definition = $this->getDefinitionOfPath($entityName, $path, $context);

        return $responseFactory->createListingResponse($criteria, $result, $definition, $request, $context);
    }

    public function create(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->write($request, $context, $responseFactory, $entityName, $path, self::WRITE_CREATE);
    }

    public function update(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->write($request, $context, $responseFactory, $entityName, $path, self::WRITE_UPDATE);
    }

    public function delete(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $pathSegments = $this->buildEntityPath($entityName, $path, $context, [WriteProtection::class]);

        $last = $pathSegments[\count($pathSegments) - 1];

        /** @var string $id id is always set, otherwise the route would not match */
        $id = $last['value'];

        /** @var EntityPathSegment $first */
        $first = array_shift($pathSegments);

        if (\count($pathSegments) === 0) {
            //first api level call /product/{id}
            $definition = $first['definition'];

            $this->executeWriteOperation($definition, ['id' => $id], $context, self::WRITE_DELETE);

            return $responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        $child = array_pop($pathSegments);
        $parent = $first;
        if (!empty($pathSegments)) {
            $parent = array_pop($pathSegments);
        }

        $definition = $child['definition'];

        /** @var AssociationField $association */
        $association = $child['field'];

        // DELETE api/product/{id}/manufacturer/{id}
        if ($association instanceof ManyToOneAssociationField || $association instanceof OneToOneAssociationField) {
            $this->executeWriteOperation($definition, ['id' => $id], $context, self::WRITE_DELETE);

            return $responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        // DELETE api/product/{id}/category/{id}
        if ($association instanceof ManyToManyAssociationField) {
            /** @var Field $local */
            $local = $definition->getFields()->getByStorageName(
                $association->getMappingLocalColumn()
            );

            /** @var Field $reference */
            $reference = $definition->getFields()->getByStorageName(
                $association->getMappingReferenceColumn()
            );

            $mapping = [
                $local->getPropertyName() => $parent['value'],
                $reference->getPropertyName() => $id,
            ];
            /** @var EntityDefinition $parentDefinition */
            $parentDefinition = $parent['definition'];

            if ($parentDefinition->isVersionAware()) {
                $versionField = $parentDefinition->getEntityName() . 'VersionId';
                $mapping[$versionField] = $context->getVersionId();
            }

            if ($association->getToManyReferenceDefinition()->isVersionAware()) {
                $versionField = $association->getToManyReferenceDefinition()->getEntityName() . 'VersionId';

                $mapping[$versionField] = Defaults::LIVE_VERSION;
            }

            $this->executeWriteOperation($definition, $mapping, $context, self::WRITE_DELETE);

            return $responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        if ($association instanceof TranslationsAssociationField) {
            /** @var EntityTranslationDefinition $refClass */
            $refClass = $association->getReferenceDefinition();

            /** @var Field $refField */
            $refField = $refClass->getFields()->getByStorageName($association->getReferenceField());
            $refPropName = $refField->getPropertyName();

            /** @var Field $langField */
            $langField = $refClass->getPrimaryKeys()->getByStorageName($association->getLanguageField());
            $refLanguagePropName = $langField->getPropertyName();

            $mapping = [
                $refPropName => $parent['value'],
                $refLanguagePropName => $id,
            ];

            $this->executeWriteOperation($definition, $mapping, $context, self::WRITE_DELETE);

            return $responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        if ($association instanceof OneToManyAssociationField) {
            $this->executeWriteOperation($definition, ['id' => $id], $context, self::WRITE_DELETE);

            return $responseFactory->createRedirectResponse($definition, $id, $request, $context);
        }

        throw new \RuntimeException(sprintf('Unsupported association for field %s', $association->getPropertyName()));
    }

    /**
     * @return array{0: Criteria, 1: EntityRepository}
     */
    private function resolveSearch(Request $request, Context $context, string $entityName, string $path): array
    {
        $pathSegments = $this->buildEntityPath($entityName, $path, $context);
        $permissions = $this->validatePathSegments($context, $pathSegments, AclRoleDefinition::PRIVILEGE_READ);

        /** @var EntityPathSegment $first */
        $first = array_shift($pathSegments);

        $definition = $first['definition'];

        $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

        $criteria = new Criteria();
        if (empty($pathSegments)) {
            $criteria = $this->criteriaBuilder->handleRequest($request, $criteria, $definition, $context);

            // trigger acl validation
            $nested = $this->criteriaValidator->validate($definition->getEntityName(), $criteria, $context);
            $permissions = array_unique(array_filter(array_merge($permissions, $nested)));

            if (!empty($permissions)) {
                throw new MissingPrivilegeException($permissions);
            }

            return [$criteria, $repository];
        }

        $child = array_pop($pathSegments);
        $parent = $first;

        if (!empty($pathSegments)) {
            /** @var EntityPathSegment $parent */
            $parent = array_pop($pathSegments);
        }

        $association = $child['field'];

        $parentDefinition = $parent['definition'];

        $definition = $child['definition'];
        if ($association instanceof ManyToManyAssociationField) {
            $definition = $association->getToManyReferenceDefinition();
        }

        $criteria = $this->criteriaBuilder->handleRequest($request, $criteria, $definition, $context);

        if ($association instanceof ManyToManyAssociationField) {
            //fetch inverse association definition for filter
            $reverses = $definition->getFields()->filter(
                fn (Field $field) => $field instanceof ManyToManyAssociationField && $association->getMappingDefinition() === $field->getMappingDefinition()
            );

            //contains now the inverse side association: category.products
            /** @var ManyToManyAssociationField|null $reverse */
            $reverse = $reverses->first();
            if (!$reverse) {
                throw new MissingReverseAssociation($definition->getEntityName(), $parentDefinition->getEntityName());
            }

            $criteria->addFilter(
                new EqualsFilter(
                    sprintf('%s.%s.id', $definition->getEntityName(), $reverse->getPropertyName()),
                    $parent['value']
                )
            );

            /** @var EntityDefinition $parentDefinition */
            if ($parentDefinition->isVersionAware()) {
                $criteria->addFilter(
                    new EqualsFilter(
                        sprintf('%s.%s.versionId', $definition->getEntityName(), $reverse->getPropertyName()),
                        $context->getVersionId()
                    )
                );
            }
        } elseif ($association instanceof OneToManyAssociationField) {
            /*
             * Example
             * Route:           /api/product/SW1/prices
             * $definition:     \Shopware\Core\Content\Product\Definition\ProductPriceDefinition
             */

            //get foreign key definition of reference
            /** @var Field $foreignKey */
            $foreignKey = $definition->getFields()->getByStorageName(
                $association->getReferenceField()
            );

            $criteria->addFilter(
                new EqualsFilter(
                    //add filter to parent value: prices.productId = SW1
                    $definition->getEntityName() . '.' . $foreignKey->getPropertyName(),
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
            $reverses = $definition->getFields()->filter(
                fn (Field $field) => $field instanceof AssociationField && $parentDefinition === $field->getReferenceDefinition()
            );
            /** @var AssociationField|null $reverse */
            $reverse = $reverses->first();
            if (!$reverse) {
                throw new MissingReverseAssociation($definition->getEntityName(), $parentDefinition->getEntityName());
            }

            $criteria->addFilter(
                new EqualsFilter(
                    //filter inverse association to parent value:  manufacturer.products.id = SW1
                    sprintf('%s.%s.id', $definition->getEntityName(), $reverse->getPropertyName()),
                    $parent['value']
                )
            );
        } elseif ($association instanceof OneToOneAssociationField) {
            /*
             * Example
             * Route:           /api/order/xxxx/orderCustomer
             * $definition:     \Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition
             */

            //get inverse association to filter to parent value
            $reverses = $definition->getFields()->filter(
                fn (Field $field) => $field instanceof OneToOneAssociationField && $parentDefinition === $field->getReferenceDefinition()
            );
            /** @var OneToOneAssociationField|null $reverse */
            $reverse = $reverses->first();
            if (!$reverse) {
                throw new MissingReverseAssociation($definition->getEntityName(), $parentDefinition->getEntityName());
            }

            $criteria->addFilter(
                new EqualsFilter(
                    //filter inverse association to parent value:  order_customer.order_id = xxxx
                    sprintf('%s.%s.id', $definition->getEntityName(), $reverse->getPropertyName()),
                    $parent['value']
                )
            );
        }

        $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

        $nested = $this->criteriaValidator->validate($definition->getEntityName(), $criteria, $context);
        $permissions = array_unique(array_filter(array_merge($permissions, $nested)));

        if (!empty($permissions)) {
            throw new MissingPrivilegeException($permissions);
        }

        return [$criteria, $repository];
    }

    private function getDefinitionOfPath(string $entityName, string $path, Context $context): EntityDefinition
    {
        $pathSegments = $this->buildEntityPath($entityName, $path, $context);

        /** @var EntityPathSegment $first */
        $first = array_shift($pathSegments);

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
            return $association->getToManyReferenceDefinition();
        }

        return $child['definition'];
    }

    private function write(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path, string $type): Response
    {
        $payload = $this->getRequestBody($request);
        $noContent = !$request->query->has('_response');
        // safari bug prevents us from using the location header
        $appendLocationHeader = false;

        if ($this->isCollection($payload)) {
            throw new BadRequestHttpException('Only single write operations are supported. Please send the entities one by one or use the /sync api endpoint.');
        }

        $pathSegments = $this->buildEntityPath($entityName, $path, $context, [WriteProtection::class]);

        $last = $pathSegments[\count($pathSegments) - 1];

        if ($type === self::WRITE_CREATE && !empty($last['value'])) {
            $methods = ['GET', 'PATCH', 'DELETE'];

            throw new MethodNotAllowedHttpException($methods, sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), implode(', ', $methods)));
        }

        if ($type === self::WRITE_UPDATE && isset($last['value'])) {
            $payload['id'] = $last['value'];
        }

        /** @var EntityPathSegment $first */
        $first = array_shift($pathSegments);

        if (\count($pathSegments) === 0) {
            $definition = $first['definition'];
            $events = $this->executeWriteOperation($definition, $payload, $context, $type);
            /** @var EntityWrittenEvent $event */
            $event = $events->getEventByEntityName($definition->getEntityName());
            $eventIds = $event->getIds();
            $entityId = array_pop($eventIds);

            if ($definition instanceof MappingEntityDefinition) {
                return new Response(null, Response::HTTP_NO_CONTENT);
            }

            if ($noContent) {
                return $responseFactory->createRedirectResponse($definition, $entityId, $request, $context);
            }

            $repository = $this->definitionRegistry->getRepository($definition->getEntityName());
            $criteria = new Criteria($event->getIds());
            $entities = $repository->search($criteria, $context);

            return $responseFactory->createDetailResponse($criteria, $entities->first(), $definition, $request, $context, $appendLocationHeader);
        }

        /** @var EntityPathSegment $child */
        $child = array_pop($pathSegments);

        $parent = $first;
        if (!empty($pathSegments)) {
            /** @var EntityPathSegment $parent */
            $parent = array_pop($pathSegments);
        }

        $definition = $child['definition'];

        $association = $child['field'];

        $parentDefinition = $parent['definition'];

        if ($association instanceof OneToManyAssociationField) {
            /** @var Field $foreignKey */
            $foreignKey = $definition->getFields()
                ->getByStorageName($association->getReferenceField());

            /** @var string $parentId, for parents the id is always set */
            $parentId = $parent['value'];
            $payload[$foreignKey->getPropertyName()] = $parentId;

            $events = $this->executeWriteOperation($definition, $payload, $context, $type);

            if ($noContent) {
                return $responseFactory->createRedirectResponse($definition, $parentId, $request, $context);
            }

            /** @var EntityWrittenEvent $event */
            $event = $events->getEventByEntityName($definition->getEntityName());

            $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

            $criteria = new Criteria($event->getIds());
            $entities = $repository->search($criteria, $context);

            return $responseFactory->createDetailResponse($criteria, $entities->first(), $definition, $request, $context, $appendLocationHeader);
        }

        if ($association instanceof ManyToOneAssociationField || $association instanceof OneToOneAssociationField) {
            $events = $this->executeWriteOperation($definition, $payload, $context, $type);
            /** @var EntityWrittenEvent $event */
            $event = $events->getEventByEntityName($definition->getEntityName());

            $entityIds = $event->getIds();
            $entityId = array_pop($entityIds);

            /** @var Field $foreignKey */
            $foreignKey = $parentDefinition->getFields()->getByStorageName($association->getStorageName());

            $payload = [
                'id' => $parent['value'],
                $foreignKey->getPropertyName() => $entityId,
            ];

            $repository = $this->definitionRegistry->getRepository($parentDefinition->getEntityName());
            $repository->update([$payload], $context);

            if ($noContent) {
                return $responseFactory->createRedirectResponse($definition, $entityId, $request, $context);
            }

            $criteria = new Criteria($event->getIds());
            $entities = $repository->search($criteria, $context);

            return $responseFactory->createDetailResponse($criteria, $entities->first(), $definition, $request, $context, $appendLocationHeader);
        }

        /** @var ManyToManyAssociationField $manyToManyAssociation */
        $manyToManyAssociation = $association;

        $reference = $manyToManyAssociation->getToManyReferenceDefinition();

        // check if we need to create the entity first
        if (\count($payload) > 1 || !\array_key_exists('id', $payload)) {
            $events = $this->executeWriteOperation($reference, $payload, $context, $type);
            /** @var EntityWrittenEvent $event */
            $event = $events->getEventByEntityName($reference->getEntityName());

            $ids = $event->getIds();
            $id = array_shift($ids);
        } else {
            // only id provided - add assignment
            $id = $payload['id'];
        }

        $payload = [
            'id' => $parent['value'],
            $manyToManyAssociation->getPropertyName() => [
                ['id' => $id],
            ],
        ];

        $repository = $this->definitionRegistry->getRepository($parentDefinition->getEntityName());
        $repository->update([$payload], $context);

        $repository = $this->definitionRegistry->getRepository($reference->getEntityName());
        $criteria = new Criteria([$id]);

        $entities = $repository->search($criteria, $context);
        $entity = $entities->first();

        if ($noContent) {
            return $responseFactory->createRedirectResponse($reference, $entity->getId(), $request, $context);
        }

        return $responseFactory->createDetailResponse($criteria, $entity, $definition, $request, $context, $appendLocationHeader);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function executeWriteOperation(
        EntityDefinition $entity,
        array $payload,
        Context $context,
        string $type
    ): EntityWrittenContainerEvent {
        $repository = $this->definitionRegistry->getRepository($entity->getEntityName());

        $conversionException = new ApiConversionException();
        $payload = $this->apiVersionConverter->convertPayload($entity, $payload, $conversionException);
        $conversionException->tryToThrow();

        $event = $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($repository, $payload, $entity, $type): ?EntityWrittenContainerEvent {
            if ($type === self::WRITE_CREATE) {
                return $repository->create([$payload], $context);
            }

            if ($type === self::WRITE_UPDATE) {
                return $repository->update([$payload], $context);
            }

            if ($type === self::WRITE_DELETE) {
                $event = $repository->delete([$payload], $context);

                if (!empty($event->getErrors())) {
                    throw new ResourceNotFoundException($entity->getEntityName(), $payload);
                }

                return $event;
            }

            return null;
        });

        if (!$event) {
            throw new \RuntimeException('Unsupported write operation.');
        }

        return $event;
    }

    /**
     * @param non-empty-list<string> $keys
     */
    private function getAssociation(FieldCollection $fields, array $keys): AssociationField
    {
        $key = array_shift($keys);

        /** @var AssociationField $field */
        $field = $fields->get($key);

        if (empty($keys)) {
            return $field;
        }

        $reference = $field->getReferenceDefinition();
        $nested = $reference->getFields();

        return $this->getAssociation($nested, $keys);
    }

    /**
     * @param list<class-string<EntityProtection>> $protections
     *
     * @return list<EntityPathSegment>
     */
    private function buildEntityPath(
        string $entityName,
        string $pathInfo,
        Context $context,
        array $protections = [ReadProtection::class]
    ): array {
        $pathInfo = str_replace('/extensions/', '/', $pathInfo);
        $exploded = explode('/', $entityName . '/' . ltrim($pathInfo, '/'));

        $parts = [];
        foreach ($exploded as $index => $part) {
            if ($index % 2) {
                continue;
            }

            if (empty($part)) {
                continue;
            }

            $value = $exploded[$index + 1] ?? null;

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

        /** @var array{'entity': string, 'value': string|null} $first */
        $first = array_shift($parts);

        try {
            $root = $this->definitionRegistry->getByEntityName($first['entity']);
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
            /** @var AssociationField|null $field */
            $field = $root->getFields()->get($part['entity']);
            if (!$field) {
                $path = implode('.', array_column($entities, 'entity')) . '.' . $part['entity'];

                throw new NotFoundHttpException(sprintf('Resource at path "%s" is not an existing relation.', $path));
            }

            if ($field instanceof ManyToManyAssociationField) {
                $root = $field->getToManyReferenceDefinition();
            } else {
                $root = $field->getReferenceDefinition();
            }

            $entities[] = [
                'entity' => $part['entity'],
                'value' => $part['value'],
                'definition' => $field->getReferenceDefinition(),
                'field' => $field,
            ];
        }

        $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($entities, $protections): void {
            $this->entityProtectionValidator->validateEntityPath($entities, $protections, $context);
        });

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
     * @return array<string, mixed>
     */
    private function getRequestBody(Request $request): array
    {
        $contentType = $request->headers->get('CONTENT_TYPE', '');
        $semicolonPosition = mb_strpos($contentType, ';');

        if ($semicolonPosition !== false) {
            $contentType = mb_substr($contentType, 0, $semicolonPosition);
        }

        try {
            switch ($contentType) {
                case 'application/vnd.api+json':
                    return $this->serializer->decode($request->getContent(), 'jsonapi');
                case 'application/json':
                    return $request->request->all();
            }
        } catch (InvalidArgumentException | UnexpectedValueException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        throw new UnsupportedMediaTypeHttpException(sprintf('The Content-Type "%s" is unsupported.', $contentType));
    }

    /**
     * @param array<mixed> $array
     */
    private function isCollection(array $array): bool
    {
        return array_keys($array) === range(0, \count($array) - 1);
    }

    private function getEntityDefinition(string $entityName): EntityDefinition
    {
        try {
            $entityDefinition = $this->definitionRegistry->getByEntityName($entityName);
        } catch (DefinitionNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return $entityDefinition;
    }

    private function validateAclPermissions(Context $context, EntityDefinition $entity, string $privilege): ?string
    {
        $resource = $entity->getEntityName();

        if ($entity instanceof EntityTranslationDefinition) {
            $resource = $entity->getParentDefinition()->getEntityName();
        }

        if (!$context->isAllowed($resource . ':' . $privilege)) {
            return $resource . ':' . $privilege;
        }

        return null;
    }

    /**
     * @param list<EntityPathSegment> $pathSegments
     *
     * @return list<string>
     */
    private function validatePathSegments(Context $context, array $pathSegments, string $privilege): array
    {
        /** @var EntityPathSegment $child */
        $child = array_pop($pathSegments);

        $missing = [];

        foreach ($pathSegments as $segment) {
            // you need detail privileges for every parent entity
            $missing[] = $this->validateAclPermissions(
                $context,
                $this->getDefinitionForPathSegment($segment),
                AclRoleDefinition::PRIVILEGE_READ
            );
        }

        $missing[] = $this->validateAclPermissions($context, $this->getDefinitionForPathSegment($child), $privilege);

        return array_unique(array_filter($missing));
    }

    /**
     * @param EntityPathSegment $segment
     */
    private function getDefinitionForPathSegment(array $segment): EntityDefinition
    {
        $definition = $segment['definition'];

        if ($segment['field'] instanceof ManyToManyAssociationField) {
            $definition = $segment['field']->getToManyReferenceDefinition();
        }

        return $definition;
    }
}
