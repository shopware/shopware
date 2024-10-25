<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('core')]
class ScaffoldingWriter
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly XmlScaffoldConfigManipulator $xmlConfigManipulator
    )
    {
    }

    public function write(StubCollection $stubCollection, PluginScaffoldConfiguration $configuration): void
    {
        /** @var Stub $stub */
        foreach ($stubCollection as $stub) {
            if ($stub->getContent() === null) {
                continue;
            }

            $configPath = $configuration->directory . '/' . $stub->getPath();
            $isXml = $this->isXml($stub->getPath());
            $stubContent = $stub->getContent();

            if($isXml && $this->filesystem->exists($configPath)) {
                $stubContent = $this->xmlConfigManipulator->addConfig(
                    $configPath,
                    $configuration->directory,
                    $stubContent
                );
            }

            $this->filesystem->dumpFile($configPath, $stubContent);
        }
    }

    private function isXml(string $getPath): bool
    {
        $extension = pathinfo($getPath, PATHINFO_EXTENSION);

        return $extension === 'xml';
    }
}
