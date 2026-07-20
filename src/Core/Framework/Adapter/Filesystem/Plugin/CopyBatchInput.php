<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\Visibility;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class CopyBatchInput
{
    /**
     * @var resource|string
     */
    private $sourceFile;

    /**
     * @param resource|string $sourceFile Passing a path is recommended, resources should not be used for large files
     * @param array<string> $targetFiles
     */
    public function __construct(
        $sourceFile,
        private readonly array $targetFiles,
        public readonly string $visibility = Visibility::PUBLIC,
    ) {
        if (!\is_resource($sourceFile) && !\is_string($sourceFile)) {
            throw AdapterException::invalidArgument(\sprintf(
                'CopyBatchInput expects first parameter to be either a resource or the filepath as a string, "%s" given.',
                \gettype($sourceFile)
            ));
        }
        $this->sourceFile = $sourceFile;
    }

    /**
     * @return string|resource
     */
    public function getSourceFile()
    {
        return $this->sourceFile;
    }

    /**
     * @return array<string>
     */
    public function getTargetFiles(): array
    {
        return $this->targetFiles;
    }
}
