<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaFolderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('content')]
class MediaFolderController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly MediaFolderService $dissolveFolderService)
    {
    }

    #[Route(path: '/api/_action/media-folder/{folderId}/dissolve', name: 'api.action.media-folder.dissolve', methods: ['POST'])]
    public function dissolve(string $folderId, Context $context): Response
    {
        $this->dissolveFolderService->dissolve($folderId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
