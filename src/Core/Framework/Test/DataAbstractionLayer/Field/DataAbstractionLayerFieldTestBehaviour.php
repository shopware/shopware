<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait DataAbstractionLayerFieldTestBehaviour
{
    abstract protected function getContainer(): ContainerInterface;

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

    protected function registerDefinitionWithExtensions(string $definitionClass, string ...$extensionsClasses): EntityDefinition
    {
        $definition = $this->registerDefinition($definitionClass);
        foreach ($extensionsClasses as $extensionsClass) {
            if ($this->getContainer()->has($extensionsClass)) {
                $extension = $this->getContainer()->get($extensionsClass);
            } else {
                $extension = new $extensionsClass();
                $this->getContainer()->set($extensionsClass, $extension);
            }

            $definition->addExtension($extension);
        }

        return $definition;
    }

    protected function removeExtension(string ...$extensionsClasses): void
    {
        foreach ($extensionsClasses as $extensionsClass) {
            /** @var EntityExtensionInterface $extension */
            $extension = new $extensionsClass();
            if ($this->getContainer()->has($extension->getDefinitionClass())) {
                /** @var EntityDefinition $definition */
                $definition = $this->getContainer()->get($extension->getDefinitionClass());

                $definition->removeExtension($extension);
            }
        }
    }
}
