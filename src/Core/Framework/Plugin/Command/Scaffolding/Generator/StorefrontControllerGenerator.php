<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\XmlScaffoldConfigManipulator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

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

    public function __construct(
        private readonly XmlScaffoldConfigManipulator $xmlConfigManipulator
    )
    {

    }

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

        $proceed = true;

        if ($this->shouldAskCliQuestion) {
            $proceed = $io->confirm(self::CLI_QUESTION);
        }

        $config->addOption(self::OPTION_NAME, $proceed);
        $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, $proceed);
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

        $stubContent = $this->xmlConfigManipulator->addConfig(
            XmlScaffoldConfigManipulator::CONFIG_TYPE_SERVICE,
            $configuration->directory . '/src/Resources/config/services.xml',
            $configuration->namespace,
            $this->servicesXmlEntry,
            'container'
        );

        $stubCollection->append(
            '/src/Resources/config/services.xml',
            $stubContent
        );

        $stubContent = $this->xmlConfigManipulator->addConfig(
            XmlScaffoldConfigManipulator::CONFIG_TYPE_ROUTE,
            $configuration->directory . '/src/Resources/config/routes.xml',
            $configuration->namespace,
            $this->routesXmlEntry,
            'routes'
        );

        $stubCollection->append(
            'src/Resources/config/routes.xml',
            $stubContent
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
