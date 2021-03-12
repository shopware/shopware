<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 * @Acl({"system.plugin_maintain"})
 */
class ExtensionStoreDataController extends AbstractController
{
    private AbstractExtensionDataProvider $extensionDataProvider;

    public function __construct(AbstractExtensionDataProvider $extensionListingProvider)
    {
        $this->extensionDataProvider = $extensionListingProvider;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/installed", name="api.extension.installed", methods={"GET"})
     */
    public function getInstalledExtensions(Context $context): Response
    {
        return new JsonResponse(
            $this->extensionDataProvider->getInstalledExtensions($context)
        );
    }
}
