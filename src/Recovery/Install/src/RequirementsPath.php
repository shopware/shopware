<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

/**
 * Example source file:
 *
 * <?xml version="1.0"?>
 * <shopware>
 *   <files>
 *     <file><name>var/log/</name></file>
 *     <file><name>config.php</name></file>
 *   </files>
 * </shopware>
 */
class RequirementsPath
{
    /**
     * @var string
     */
    private $basePath;

    /**
     * @var array
     */
    private $files;

    /**
     * @param string $basePath
     * @param string $sourceFile
     */
    public function __construct($basePath, $sourceFile)
    {
        $this->basePath = rtrim($basePath, '/') . '/';

        $this->files = $this->readList($sourceFile);
    }

    public function addFile($file): void
    {
        $this->files[] = $file;
    }

    public function check(): RequirementsPathResult
    {
        $result = [];

        foreach ($this->files as $file) {
            $entry['name'] = $file === '.' ? $this->basePath : $file;
            $entry['existsAndWriteable'] = $this->checkExits($file);
            $result[] = $entry;
        }

        return new RequirementsPathResult($result);
    }

    private function readList(string $sourceFile): array
    {
        $checks = include $sourceFile;

        return $checks['paths'];
    }

    private function checkExits(string $name): bool
    {
        $name = $this->basePath . $name;

        return file_exists($name) && is_readable($name) && is_writable($name);
    }
}
