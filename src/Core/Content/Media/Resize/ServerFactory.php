<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Resize;

use Intervention\Image\ImageManager;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Glide\Api\Api;
use League\Glide\Manipulators;
use League\Glide\Server;

class ServerFactory
{
    /**
     * @var FilesystemInterface
     */
    private $source;

    /**
     * @var string
     */
    private $cacheDir;

    public function __construct(FilesystemInterface $source, string $cacheDir)
    {
        $this->source = $source;
        $this->cacheDir = $cacheDir;
    }

    public function create(): Server
    {
        $server = new Server(
            $this->source,
            $this->createCache(),
            $this->createApi()
        );

        return $server;
    }

    private function createImageManager(): ImageManager
    {
        return new ImageManager([
            'driver' => 'gd',
        ]);
    }

    /**
     * @return Manipulators\ManipulatorInterface[]
     */
    private function createManipulators(): array
    {
        return [
            new Manipulators\Size(600 * 600),
        ];
    }

    private function createApi(): Api
    {
        return new Api(
            $this->createImageManager(),
            $this->createManipulators()
        );
    }

    private function createCache(): Filesystem
    {
        return new Filesystem(
            new Local($this->cacheDir . '/glide/')
        );
    }
}
