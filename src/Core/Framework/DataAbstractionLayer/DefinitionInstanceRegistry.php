<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Symfony\Component\DependencyInjection\Container;

class DefinitionInstanceRegistry
{
    /**
     * @var Container
     */
    private $container;
    /**
     * @var array
     */
    private $definitions;

    public function __construct(Container $container, array $definitions)
    {
        $this->container = $container;
        $this->definitions = $definitions;
    }

    public function get(string $name): EntityDefinition
    {
        return $this->container->get($name);
    }
}
