<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @RouteScope(scopes={"api"})
 */
class CustomEntityApiController extends ApiController
{
    public function clone(Context $context, string $entity, string $id, Request $request): JsonResponse
    {
        $entity = 'custom-entity-' . $entity;

        return parent::clone($context, $entity, $id, $request);
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
