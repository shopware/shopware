<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;
use Shopware\Core\Framework\Event\Annotation\Event;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\BusinessEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ActionEventCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $classes = [];
        /** @var BusinessEventInterface $eventClass */
        foreach ($this->getEventClasses() as $eventClass) {
            if (!is_subclass_of($eventClass, BusinessEventInterface::class, true)) {
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
            foreach ($docParser->parse($constant->getDocComment(), $reflectionClass->getName()) as $annotation) {
                if ($annotation instanceof Event) {
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
