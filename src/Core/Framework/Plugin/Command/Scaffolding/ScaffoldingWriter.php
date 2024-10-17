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

            if($isXml) {
                $configType = $this->resolveConfigType($stub->getPath());
                $rootNodeName = $this->resolveRootNodeName($stub->getPath());
                $stubContent = $this->xmlConfigManipulator->addConfig(
                    $configType,
                    $configPath,
                    $configuration->directory,
                    $stubContent,
                    $rootNodeName
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

    private function resolveConfigType(string $getPath): string
    {
        $filename = pathinfo($getPath, PATHINFO_FILENAME);

        return $filename === 'services' ? XmlScaffoldConfigManipulator::CONFIG_TYPE_SERVICE : XmlScaffoldConfigManipulator::CONFIG_TYPE_ROUTE;
    }

    private function resolveRootNodeName(string $getPath): string
    {
        $configType = pathinfo($getPath, PATHINFO_FILENAME);

        return $configType === 'services' ? 'container' : 'routes';
    }
}
