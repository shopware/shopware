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
class JavascriptPluginGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-javascript-plugin';
    private const OPTION_TITLE = 'Javascript Plugin';
    private const OPTION_DESCRIPTION = 'Create an example javascript plugin';
    private const OPTION_DESCRIPTION_LONG = 'A custom Storefront JavaScript plugin is an ES6 class that extends Shopware\'s plugin base class and adds client-side functionality to the storefront. Use one when you need to implement custom interactions or behavior, such as reacting to user actions or DOM events.';
    private const CLI_QUESTION = 'Do you want to create an example javascript plugin?';

    private string $mainTsEntry = <<<'EOL'
    // Import all necessary Storefront plugins
    import PluginManager from 'src/plugin-system/plugin.manager';
    import ExamplePlugin from './example-plugin/example-plugin.plugin';

    // Register your plugin via the PluginManager
    PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-example-plugin]');

    EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createPluginFile());
        $stubCollection->add($this->createTemplate());

        $stubCollection->append(
            'src/Resources/app/storefront/src/main.ts',
            $this->mainTsEntry
        );
    }

    private function createPluginFile(): Stub
    {
        return Stub::template(
            'src/Resources/app/storefront/src/example-plugin/example-plugin.plugin.ts',
            self::STUB_DIRECTORY . '/ts-plugin.stub'
        );
    }

    private function createTemplate(): Stub
    {
        return Stub::template(
            'src/Resources/views/storefront/page/content/index.html.twig',
            self::STUB_DIRECTORY . '/js-plugin-template.stub'
        );
    }
}
