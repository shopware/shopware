<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Api;

use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"test"})
 */
class CatchAllMediaController extends AbstractController
{
    /**
     * @var Filesystem
     */
    private $publicFilesystem;

    public function __construct(FileSystem $publicFilesystem)
    {
        $this->publicFilesystem = $publicFilesystem;
    }

    /**
     * @Route("/{path}", requirements={"path"="(media|thumbnail|theme)/.+"}, name="test.media.catch_all" )
     */
    public function publicFilesystem(string $path): Response
    {
        $this->publicFilesystem->writeStream($path, fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb'));

        if (!$this->publicFilesystem->has($path)) {
            throw new NotFoundHttpException($path . ' not found');
        }
        $pathInfos = pathinfo($path);
        $mimeType = $this->publicFilesystem->getMimetype($path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $pathInfos['basename'],
        ];

        $streamResponse = new StreamedResponse(function () use ($path): void {
            $stream = $this->publicFilesystem->readStream($path);
            while (!feof($stream)) {
                $content = fread($stream, 4096);
                if ($content !== false) {
                    echo $content;
                }

                flush();
            }
            fclose($stream);
        }, 200, $headers);

        return $streamResponse;
    }
}
