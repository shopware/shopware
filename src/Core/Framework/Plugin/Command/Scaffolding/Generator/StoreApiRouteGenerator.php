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
class StoreApiRouteGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-store-api-route';
    private const OPTION_DESCRIPTION = 'Create an example store-api route';
    private const CLI_QUESTION = 'Do you want to create an example store-api route?';

    private string $servicesXmlEntry = <<<'EOL'

            <service id="{{ namespace }}\Core\Content\Example\SalesChannel\ExampleRoute">
                <argument type="service" id="product.repository"/>
            </service>

    EOL;

    private string $routesXmlEntry = <<<'EOL'

        <import resource="../../Core/**/*Route.php" type="attribute" />

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

        $stubCollection->add($this->createAbstractStoreApiRoute($configuration));
        $stubCollection->add($this->createStoreApiRoute($configuration));
        $stubCollection->add($this->createStoreApiRouteResponse($configuration));

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

    private function createAbstractStoreApiRoute(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/SalesChannel/AbstractExampleRoute.php',
            self::STUB_DIRECTORY . '/store-api-abstract-route.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }

    private function createStoreApiRoute(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/SalesChannel/ExampleRoute.php',
            self::STUB_DIRECTORY . '/store-api-route.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }

    private function createStoreApiRouteResponse(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/SalesChannel/ExampleRouteResponse.php',
            self::STUB_DIRECTORY . '/store-api-response.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
