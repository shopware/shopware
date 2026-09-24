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
class TestsGenerator implements ScaffoldingGenerator
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
        $stubCollection->add($this->createPhpunitXml($configuration));
        $stubCollection->add($this->createTestBootstrap($configuration));
    }

    private function createPhpunitXml(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'phpunit.xml',
            self::STUB_DIRECTORY . '/phpunit-xml.stub',
            [
                'className' => $configuration->name,
            ]
        );
    }

    private function createTestBootstrap(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'tests/TestBootstrap.php',
            self::STUB_DIRECTORY . '/test-bootstrap.stub',
            [
                'namespace' => str_replace('\\', '\\\\', $configuration->namespace),
                'className' => $configuration->name,
            ]
        );
    }
}
