<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('core')]
class StorefrontControllerGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-storefront-controller';
    private const OPTION_DESCRIPTION = 'Create an example storefront controller';
    private const CLI_QUESTION = 'Do you want to create an example storefront controller?';

    private string $servicesXmlEntry = <<<'EOL'

            <service id="{{ namespace }}\Storefront\Controller\ExampleController" public="true">
                <call method="setContainer">
                    <argument type="service" id="service_container"/>
                </call>
                <call method="setTwig">
                     <argument type="service" id="twig"/>
                 </call>
            </service>

    EOL;

    private string $routesXmlEntry = <<<'EOL'

        <import resource="../../Storefront/Controller/**/*Controller.php" type="attribute" />

    EOL;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);

            return;
        }

        if ($this->shouldAskCliQuestion && $io->confirm(self::CLI_QUESTION)) {
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
            'src/Resources/config/services.xml',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesXmlEntry
            )
        );

        $stubCollection->append(
            'src/Resources/config/routes.xml',
            $this->routesXmlEntry
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
