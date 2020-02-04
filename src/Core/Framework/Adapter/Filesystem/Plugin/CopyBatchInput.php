<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

class CopyBatchInput
{
    /**
     * @var string
     */
    private $sourceFile;

    /**
     * @var string[]
     */
    private $targetFiles;

    /**
     * @param string[] $targetFiles
     */
    public function __construct(string $sourceFile, array $targetFiles)
    {
        $this->sourceFile = $sourceFile;
        $this->targetFiles = $targetFiles;
    }

    public function getSourceFile(): string
    {
        return $this->sourceFile;
    }

    /**
     * @return string[]
     */
    public function getTargetFiles(): array
    {
        return $this->targetFiles;
    }
}
