<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util\PathnameStrategy;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Filesystem\AbstractFilesystem;

class StrategyFilesystem extends AbstractFilesystem
{
    /**
     * @var PathnameStrategyInterface
     */
    private $strategy;

    /**
     * @param FilesystemInterface       $filesystem
     * @param PathnameStrategyInterface $strategy
     */
    public function __construct(FilesystemInterface $filesystem, PathnameStrategyInterface $strategy)
    {
        parent::__construct($filesystem);

        $this->strategy = $strategy;
    }

    public function preparePath(string $path): string
    {
        $parts = pathinfo($path);

        return $this->strategy->encode($parts['filename']) . '.' . $parts['extension'];
    }

    public function stripPath(string $path): string
    {
        $parts = pathinfo($path);

        return $this->strategy->decode($parts['filename']) . '.' . $parts['extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        throw new \RuntimeException('Calling listContents() on a StrategyFilesystem is not supported.');
    }
}
