<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;
use Shopware\Core\Framework\Event\Annotation\Event;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class ActionEventCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $classes = [];
        foreach ($this->getEventClasses() as $eventClass) {
            if (!is_subclass_of($eventClass, FlowEventAware::class, true)) {
                continue;
            }

            $classes[] = $eventClass;
        }

        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addClasses', [$classes]);
    }

    protected function getReflectionClass(): \ReflectionClass
    {
        return new \ReflectionClass(BusinessEvents::class);
    }

    private function getEventClasses(): array
    {
        $reflectionClass = $this->getReflectionClass();
        $docParser = $this->getDocParser();

        $eventClasses = [];
        foreach ($reflectionClass->getReflectionConstants() as $constant) {
            foreach ($docParser->parse($constant->getDocComment()) as $annotation) {
                if ($annotation instanceof Event) {
                    $deprecationVersion = $annotation->getDeprecationVersion();

                    if ($deprecationVersion && Feature::isActive($deprecationVersion)) {
                        continue;
                    }

                    $eventClasses[$constant->getValue()] = $annotation->getEventClass();
                }
            }
        }

        return $eventClasses;
    }

    private function getDocParser(): DocParser
    {
        $docParser = new DocParser();
        $docParser->setImports([
            'event' => Event::class,
        ]);
        $docParser->setIgnoreNotImportedAnnotations(true);
        AnnotationRegistry::registerLoader('class_exists');

        return $docParser;
    }
}
