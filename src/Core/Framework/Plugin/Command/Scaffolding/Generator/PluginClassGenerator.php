<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('framework')]
class PluginClassGenerator implements ScaffoldingGenerator
{
    public function hasCommandOption(): bool
    {
        return false;
    }

    public function getCommandOptionName(): string
    {
        return '';
    }

    public function getCommandOptionDescription(): string
    {
        return '';
    }

    public function getCommandOptionTitle(): string
    {
        return '';
    }

    public function getCommandOptionDescriptionLong(): string
    {
        return '';
    }

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        $stubCollection->add($this->createPluginClass($configuration));
    }

    private function createPluginClass(PluginScaffoldConfiguration $configuration): Stub
    {
        $pluginClassPath = 'src/' . $configuration->name . '.php';

        return Stub::template(
            $pluginClassPath,
            self::STUB_DIRECTORY . '/plugin-class.stub',
            [
                'namespace' => $configuration->namespace,
                'className' => $configuration->name,
            ]
        );
    }
}
