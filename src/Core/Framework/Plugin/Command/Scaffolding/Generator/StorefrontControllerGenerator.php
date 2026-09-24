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
class StorefrontControllerGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-storefront-controller';
    private const OPTION_TITLE = 'Storefront Controller';
    private const OPTION_DESCRIPTION = 'Create an example storefront controller';
    private const OPTION_DESCRIPTION_LONG = 'A custom Storefront controller is a class that handles requests for a defined URL route in the Shopware storefront. Use one when your plugin needs to provide custom pages, endpoints, or dynamic content that is not covered by Shopware\'s existing routes.';
    private const CLI_QUESTION = 'Do you want to create an example storefront controller?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\Storefront\Controller\ExampleController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

EOL;

    private string $routesPhpEntry = <<<'EOL'

    $routes->import('../../Storefront/Controller/**/*Controller.php', 'attribute');

EOL;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        OutputInterface $output
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);

            return;
        }

        if ($this->askCliQuestion($input, $output, self::CLI_QUESTION)) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);
        }
    }

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createController($configuration));
        $stubCollection->add($this->createTemplate());

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesPhpEntry
            )
        );

        $stubCollection->append(
            'src/Resources/config/routes.php',
            $this->routesPhpEntry
        );
    }

    private function createController(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Storefront/Controller/ExampleController.php',
            self::STUB_DIRECTORY . '/storefront-controller.stub',
            [
                'namespace' => $configuration->namespace,
                'className' => $configuration->name,
            ]
        );
    }

    private function createTemplate(): Stub
    {
        return Stub::template(
            'src/Resources/views/storefront/page/example.html.twig',
            self::STUB_DIRECTORY . '/storefront-template.stub'
        );
    }
}
