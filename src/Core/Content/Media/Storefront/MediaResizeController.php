<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Storefront;

use League\Flysystem\Filesystem;
use League\Glide\Server;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MediaResizeController extends AbstractController
{
    /**
     * @var Server
     */
    private $resizeServer;

    /**
     * @var Filesystem
     */
    private $sourceFolder;

    public function __construct(Server $resizeServer, Filesystem $sourceFolder)
    {
        $this->resizeServer = $resizeServer;
        $this->sourceFolder = $sourceFolder;
    }

    /**
     * @Route("/media/resize/{fileName}", name="storefront.action.media.resize", methods={"GET"}, requirements={"fileName"=".+"})
     */
    public function resize(?string $fileName): Response
    {
        if (!$this->sourceFolder->has($fileName)) {
            return new Response(
                'File not found!',
                404
            );
        }

        $this->resizeServer->outputImage($fileName, [
            'w' => 100,
        ]);

        /* could also be handeled by a response-factory
         * @see https://glide.thephpleague.com/1.0/config/responses/
         */
        exit;
    }
}
