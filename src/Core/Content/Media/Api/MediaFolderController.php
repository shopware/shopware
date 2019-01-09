<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\DissolveMediaFolderService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MediaFolderController extends AbstractController
{
    /**
     * @var DissolveMediaFolderService
     */
    private $dissolveFolderService;

    public function __construct(DissolveMediaFolderService $dissolveFolderService)
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
}
