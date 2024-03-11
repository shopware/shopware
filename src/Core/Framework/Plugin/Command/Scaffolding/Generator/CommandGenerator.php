<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('core')]
class CommandGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-command';
    private const OPTION_DESCRIPTION = 'Create an example console command';
    private const CLI_QUESTION = 'Do you want to create an example console command?';

    private string $servicesXmlEntry = <<<'EOL'

            <service id="{{ namespace }}\Command\ExampleCommand">
                <tag name="console.command"/>
            </service>

    EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createCommand($configuration));

        $stubCollection->append(
            'src/Resources/config/services.xml',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesXmlEntry
            )
        );
    }

    private function createCommand(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Command/ExampleCommand.php',
            self::STUB_DIRECTORY . '/command.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
