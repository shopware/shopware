<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaFolderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class MediaFolderController extends AbstractController
{
    /**
     * @var MediaFolderService
     */
    private $dissolveFolderService;

    public function __construct(MediaFolderService $dissolveFolderService)
    {
        $this->dissolveFolderService = $dissolveFolderService;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/v{version}/_action/media-folder/{folderId}/dissolve", name="api.action.media-folder.dissolve", methods={"POST"})
     */
    public function dissolve(string $folderId, Context $context): Response
    {
        $this->dissolveFolderService->dissolve($folderId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
