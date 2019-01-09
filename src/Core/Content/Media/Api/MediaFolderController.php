<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaFolderService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * @Route("/api/v{version}/_action/media-folder/{folderId}/dissolve", name="api.action.media-folder.dissolve", methods={"POST"})
     *
     * @return Response
     */
    public function dissolve(string $folderId, Context $context): Response
    {
        $this->dissolveFolderService->dissolve($folderId, $context);

        return new Response();
    }

    /**
     * @Route("/api/v{version}/_action/media-folder/{folderId}/move/{targetFolderId}",
     *     defaults={"targetFolderId"=null},
     *     name="api.action.media-folder.move", methods={"POST"})
     *
     * @return Response
     */
    public function move(string $folderId, ?string $targetFolderId, Context $context): Response
    {
        $this->dissolveFolderService->move($folderId, $targetFolderId, $context);

        return new Response();
    }
}
