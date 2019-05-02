<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait DataAbstractionLayerFieldTestBehaviour
{
    abstract public function getContainer(): ContainerInterface;

    protected function registerDefinition(string ...$definitionClasses): EntityDefinition
    {
        $ret = null;

        foreach ($definitionClasses as $definitionClass) {
            if ($this->getContainer()->has($definitionClass)) {
                $definition = $this->getContainer()->get($definitionClass);
            } else {
                $definition = new $definitionClass();
                $this->getContainer()->set($definitionClass, $definition);
                $definition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
            }

            if ($ret === null) {
                $ret = $definition;
            }
        }

        return $ret;
    }
}
