<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
class ConfigGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-plugin-config';
    private const OPTION_TITLE = 'Plugin Config';
    private const OPTION_DESCRIPTION = 'Create an example plugin config';
    private const OPTION_DESCRIPTION_LONG = 'A plugin config.xml file defines a settings form that Shopware shows in the Administration for your plugin. Use one when merchants should configure values such as API keys, feature toggles, or other options through the system config instead of hard-coding them.';
    private const CLI_QUESTION = 'Do you want to create an example plugin config?';

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createConfig());
    }

    private function createConfig(): Stub
    {
        return Stub::template(
            'src/Resources/config/config.xml',
            self::STUB_DIRECTORY . '/config-xml.stub'
        );
    }
}
