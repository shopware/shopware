<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ReadProtectedException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @deprecated tag:v6.4.0 - Use Store-API instead
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelApiController
{
    private const PROTECTION_BLACKLIST = [
        CustomerDefinition::class,
        UserDefinition::class,
        PluginDefinition::class,
        SalesChannelDefinition::class,
        AclRoleDefinition::class,
        AclUserRoleDefinition::class,
        UserRecoveryDefinition::class,
    ];

    /**
     * @var SalesChannelDefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(
        SalesChannelDefinitionInstanceRegistry $registry,
        RequestCriteriaBuilder $criteriaBuilder,
        ApiVersionConverter $apiVersionConverter
    ) {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->apiVersionConverter = $apiVersionConverter;
    }

    public function searchIds(Request $request, SalesChannelContext $context, string $entity): Response
    {
        $entity = $this->urlToSnakeCase($entity);
        $this->checkIfRouteAvailableInApiVersion($entity, $request->attributes->getInt('version'));

        $repository = $this->registry->getSalesChannelRepository($entity);

        /** @var SalesChannelDefinitionInterface|EntityDefinition $definition */
        $definition = $this->registry->getByEntityName($entity);

        $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $definition, $context->getContext());

        $criteria = $this->checkProtectedAssociations($criteria, $definition);

        $definition->processCriteria($criteria, $context);

        $result = $repository->searchIds($criteria, $context);

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => array_values($result->getIds()),
        ]);
    }

    /**
     * @throws InvalidUuidException
     * @throws ResourceNotFoundException
     */
    public function detail(Request $request, string $id, SalesChannelContext $context, string $entity, ResponseFactoryInterface $responseFactory): Response
    {
        $entity = $this->urlToSnakeCase($entity);
        $this->checkIfRouteAvailableInApiVersion($entity, $request->attributes->getInt('version'));

        $repository = $this->registry->getSalesChannelRepository($entity);
        /** @var SalesChannelDefinitionInterface|EntityDefinition $definition */
        $definition = $this->registry->getByEntityName($entity);

        if (!Uuid::isValid($id)) {
            throw new InvalidUuidException($id);
        }

        $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria([$id]), $definition, $context->getContext());

        $criteria = $this->checkProtectedAssociations($criteria, $definition);

        $definition->processCriteria($criteria, $context);

        $result = $repository->search($criteria, $context);

        if (!$result->has($id)) {
            throw new ResourceNotFoundException($definition->getEntityName(), ['id' => $id]);
        }

        return $responseFactory->createDetailResponse($criteria, $result->get($id), $definition, $request, $context->getContext());
    }

    public function search(Request $request, SalesChannelContext $context, string $entity, ResponseFactoryInterface $responseFactory): Response
    {
        $entity = $this->urlToSnakeCase($entity);
        $this->checkIfRouteAvailableInApiVersion($entity, $request->attributes->getInt('version'));

        $repository = $this->registry->getSalesChannelRepository($entity);
        /** @var SalesChannelDefinitionInterface|EntityDefinition $definition */
        $definition = $this->registry->getByEntityName($entity);

        $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $definition, $context->getContext());

        $criteria = $this->checkProtectedAssociations($criteria, $definition);

        $definition->processCriteria($criteria, $context);

        $result = $repository->search($criteria, $context);

        return $responseFactory->createListingResponse($criteria, $result, $definition, $request, $context->getContext());
    }

    private function urlToSnakeCase(string $name): string
    {
        return str_replace('-', '_', $name);
    }

    private function checkProtectedAssociations(Criteria $criteria, EntityDefinition $definition): Criteria
    {
        foreach ($criteria->getAssociations() as $entityName => $associationCriteria) {
            $field = $definition->getField($entityName);
            if (!$field || !$field instanceof AssociationField) {
                continue;
            }

            $referenceDefinition = $field->getReferenceDefinition();
            if ($field instanceof ManyToManyAssociationField) {
                $referenceDefinition = $field->getToManyReferenceDefinition();
            }

            /* @var AssociationField $field */
            $this->checkProtectedAssociations($associationCriteria, $referenceDefinition);
        }

        $aggregationAccessors = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            foreach ($aggregation->getFields() as $field) {
                $aggregationAccessors[] = $field;
            }
        }
        $acessors = array_merge(
            $criteria->getSearchQueryFields(),
            array_keys($criteria->getAssociations()),
            $aggregationAccessors
        );

        foreach ($acessors as $acessor) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $acessor);
            /** @var Field $field */
            foreach ($fields as $field) {
                /** @var ReadProtected|null $flag */
                $flag = $field->getFlag(ReadProtected::class);

                if ($flag && !$flag->isSourceAllowed(SalesChannelApiSource::class)) {
                    throw new ReadProtectedException($field->getPropertyName(), SalesChannelApiSource::class);
                }

                if (!$field instanceof AssociationField) {
                    continue;
                }

                $referenceDefinition = $field->getReferenceDefinition();
                if ($field instanceof ManyToManyAssociationField) {
                    $referenceDefinition = $field->getToManyReferenceDefinition();
                }

                if (in_array($referenceDefinition->getClass(), self::PROTECTION_BLACKLIST, true)) {
                    throw new ReadProtectedException($field->getPropertyName(), SalesChannelApiSource::class);
                }
            }
        }

        return $criteria;
    }

    private function checkIfRouteAvailableInApiVersion(string $entity, int $version): void
    {
        if (!$this->apiVersionConverter->isAllowed($entity, null, $version)) {
            throw new NotFoundHttpException();
        }
    }
}
