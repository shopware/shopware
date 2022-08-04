<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

class CopyBatchInput
{
    /**
     * @var string|resource
     */
    private $sourceFile;

    /**
     * @var array<string>
     */
    private $targetFiles;

    /**
     * @param string|resource $sourceFile
     * @param array<string>        $targetFiles
     */
    public function __construct($sourceFile, array $targetFiles)
    {
        if (!\is_resource($sourceFile) && !\is_string($sourceFile)) {
            throw new \InvalidArgumentException(sprintf(
                'CopyBatchInpit expects first parameter to be either a resource or the filepath as a string, "%s" given.',
                \gettype($sourceFile)
            ));
        }
        $this->sourceFile = $sourceFile;
        $this->targetFiles = $targetFiles;
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
