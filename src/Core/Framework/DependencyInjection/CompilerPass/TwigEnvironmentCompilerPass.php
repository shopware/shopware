<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TwigEnvironmentCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $twigEnvironment = $container->findDefinition('twig');
        $twigEnvironment->setClass(TwigEnvironment::class);
    }
}
