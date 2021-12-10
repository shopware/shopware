<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompositeEntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"api"})
 */
class CustomEntityApiController extends ApiController
{
    public function __construct(DefinitionInstanceRegistry $definitionRegistry, Serializer $serializer, RequestCriteriaBuilder $searchCriteriaBuilder, CompositeEntitySearcher $compositeEntitySearcher, ApiVersionConverter $apiVersionConverter, EntityProtectionValidator $entityProtectionValidator, AclCriteriaValidator $criteriaValidator)
    {
        parent::__construct($definitionRegistry, $serializer, $searchCriteriaBuilder, $compositeEntitySearcher, $apiVersionConverter, $entityProtectionValidator, $criteriaValidator);
    }

    public function clone(Context $context, string $entityName, string $id, Request $request): JsonResponse
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::clone($context, $entityName, $id, $request);
    }

    public function detail(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::detail($request, $context, $responseFactory, $entityName, $path);
    }

    public function searchIds(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::searchIds($request, $context, $responseFactory, $entityName, $path);
    }

    public function search(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::search($request, $context, $responseFactory, $entityName, $path);
    }

    public function list(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::list($request, $context, $responseFactory, $entityName, $path);
    }

    public function create(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::create($request, $context, $responseFactory, $entityName, $path);
    }

    public function update(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::update($request, $context, $responseFactory, $entityName, $path);
    }

    public function delete(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::delete($request, $context, $responseFactory, $entityName, $path);
    }
}
