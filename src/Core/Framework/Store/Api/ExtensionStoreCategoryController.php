<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Services\AbstractStoreCategoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 * @Acl({"system.plugin_maintain"})
 */
class ExtensionStoreCategoryController extends AbstractController
{
    private AbstractStoreCategoryProvider $storeCategoryProvider;

    public function __construct(AbstractStoreCategoryProvider $storeCategoryProvider)
    {
        $this->storeCategoryProvider = $storeCategoryProvider;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/store-categories", name="api.extension.store_categories", Methods={"GET"})
     */
    public function getCategories(Context $context): JsonResponse
    {
        return new JsonResponse($this->storeCategoryProvider->getCategories($context));
    }
}
