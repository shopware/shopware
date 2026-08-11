<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ScaffoldingWriter
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    public function write(StubCollection $stubCollection, PluginScaffoldConfiguration $configuration): void
    {
        /** @var Stub $stub */
        foreach ($stubCollection as $stub) {
            $content = $stub->getContent();

            if ($content === null) {
                continue;
            }

            $file = $configuration->directory . '/' . $stub->getPath();

            if ($stub->getType() === Stub::TYPE_APPEND) {
                $this->append($file, $content);

                continue;
            }

            if ($this->filesystem->exists($file)) {
                continue;
            }

            $this->filesystem->dumpFile($file, $content);
        }
    }

    private function append(string $file, string $content): void
    {
        if (!$this->filesystem->exists($file)) {
            $this->filesystem->dumpFile($file, $content);

            return;
        }

        $existing = $this->filesystem->readFile($file);

        if (str_contains($existing, $content)) {
            return;
        }

        $position = strrpos($existing, '};');

        if ($position === false) {
            $this->filesystem->appendToFile($file, $content);

            return;
        }

        $this->filesystem->dumpFile($file, substr_replace($existing, $content, $position, 0));
    }
}
