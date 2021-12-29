<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BusinessEventRegisterCompilerPass implements CompilerPassInterface
{
    /**
     * @var class-string[]
     */
    private array $classes;

    public function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addClasses', [$this->classes]);
    }
}
