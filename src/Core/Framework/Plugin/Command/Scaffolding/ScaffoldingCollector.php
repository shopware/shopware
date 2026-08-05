<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;

/**
 * @internal
 */
#[Package('framework')]
class ScaffoldingCollector
{
    private string $servicesPhpIntro = <<<'EOL'
<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

EOL;

    private string $servicesPhpOutro = <<<'EOL'
};

EOL;

    private string $routesPhpIntro = <<<'EOL'
<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {

EOL;

    private string $routesPhpOutro = <<<'EOL'
};

EOL;

    /**
     * @param iterable<ScaffoldingGenerator> $generators
     */
    public function __construct(private readonly iterable $generators)
    {
    }

    public function collect(PluginScaffoldConfiguration $configuration): StubCollection
    {
        $stubCollection = new StubCollection();

        $stubCollection->add(Stub::raw(
            'src/Resources/config/services.php',
            $this->servicesPhpIntro
        ));

        if ($configuration->hasOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME)) {
            $stubCollection->add(Stub::raw(
                'src/Resources/config/routes.php',
                $this->routesPhpIntro
            ));
        }

        foreach ($this->generators as $generator) {
            $generator->generateStubs($configuration, $stubCollection);
        }

        $stubCollection->append(
            'src/Resources/config/services.php',
            $this->servicesPhpOutro
        );

        if ($configuration->hasOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME)) {
            $stubCollection->append(
                'src/Resources/config/routes.php',
                $this->routesPhpOutro
            );
        }

        return $stubCollection;
    }
}
