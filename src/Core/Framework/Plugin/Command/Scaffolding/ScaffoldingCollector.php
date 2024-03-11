<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;

/**
 * @internal
 */
#[Package('core')]
class ScaffoldingCollector
{
    private string $servicesXmlIntro = <<<'EOL'
    <?xml version="1.0" ?>

    <container xmlns="http://symfony.com/schema/dic/services"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

        <services>

    EOL;

    private string $servicesXmlOutro = <<<'EOL'

        </services>
    </container>
    EOL;

    private string $routesXmlIntro = <<<'EOL'
    <?xml version="1.0" encoding="UTF-8" ?>

    <routes xmlns="http://symfony.com/schema/routing"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="http://symfony.com/schema/routing
            https://symfony.com/schema/routing/routing-1.0.xsd">

    EOL;

    private string $routesXmlOutro = <<<'EOL'

    </routes>
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
            'src/Resources/config/services.xml',
            $this->servicesXmlIntro
        ));

        if ($configuration->hasOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME)) {
            $stubCollection->add(Stub::raw(
                'src/Resources/config/routes.xml',
                $this->routesXmlIntro
            ));
        }

        foreach ($this->generators as $generator) {
            $generator->generateStubs($configuration, $stubCollection);
        }

        $stubCollection->append(
            'src/Resources/config/services.xml',
            $this->servicesXmlOutro
        );

        if ($configuration->hasOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME)) {
            $stubCollection->append(
                'src/Resources/config/routes.xml',
                $this->routesXmlOutro
            );
        }

        return $stubCollection;
    }
}
