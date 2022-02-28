<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Api;

use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 */
class CustomEntityApiController extends ApiController
{
    /**
     * @Route(
     *     "/api/custom-entity-{entityName}/{path}",
     *     name="api.custom_entity_entity.detail",
     *     requirements={"path"="[0-9a-f]{32}(\/(extensions\/)?[a-zA-Z-]+\/[0-9a-f]{32})*\/?$"},
     *     methods={"GET"}
     * )
     */
    public function detail(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::detail($request, $context, $responseFactory, $entityName, $path);
    }

    /**
     * @Route(
     *     "/api/search-ids/custom-entity-{entityName}{path}",
     *     name="api.custom_entity_entity.search-ids",
     *     requirements={"path"="(\/[0-9a-f]{32}\/(extensions\/)?[a-zA-Z-]+)*\/?$"},
     *     methods={"POST"}
     * )
     */
    public function searchIds(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::searchIds($request, $context, $responseFactory, $entityName, $path);
    }

    /**
     * @Route(
     *     "/api/search/custom-entity-{entityName}{path}",
     *     name="api.custom_entity_entity.search",
     *     requirements={"path"="(\/[0-9a-f]{32}\/(extensions\/)?[a-zA-Z-]+)*\/?$"},
     *     methods={"POST"}
     * )
     */
    public function search(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::search($request, $context, $responseFactory, $entityName, $path);
    }

    /**
     * @Route(
     *     "/api/custom-entity-{entityName}{path}",
     *     name="api.custom_entity_entity.list",
     *     requirements={"path"="(\/[0-9a-f]{32}\/(extensions\/)?[a-zA-Z-]+)*\/?$"},
     *     methods={"GET"}
     * )
     */
    public function list(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::list($request, $context, $responseFactory, $entityName, $path);
    }

    /**
     * @Route(
     *      "/api/custom-entity-{entityName}{path}",
     *     name="api.custom_entity_entity.create",
     *     requirements={"path"="(\/[0-9a-f]{32}\/(extensions\/)?[a-zA-Z-]+)*\/?$"},
     *     methods={"POST"}
     * )
     */
    public function create(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::create($request, $context, $responseFactory, $entityName, $path);
    }

    /**
     * @Route(
     *     "/api/custom-entity-{entityName}/{path}",
     *     name="api.custom_entity_entity.update",
     *     requirements={"path"="[0-9a-f]{32}(\/(extensions\/)?[a-zA-Z-]+\/[0-9a-f]{32})*\/?$"},
     *     methods={"PATCH"}
     * )
     */
    public function update(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::update($request, $context, $responseFactory, $entityName, $path);
    }

    /**
     * @Route(
     *     "/api/custom-entity-{entityName}/{path}",
     *     name="api.custom_entity_entity.delete",
     *     requirements={"path"="[0-9a-f]{32}(\/(extensions\/)?[a-zA-Z-]+\/[0-9a-f]{32})*\/?$"},
     *     methods={"DELETE"}
     * )
     */
    public function delete(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        $entityName = 'custom-entity-' . $entityName;

        return parent::delete($request, $context, $responseFactory, $entityName, $path);
    }
}
