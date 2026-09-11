<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[Package('framework')]
class TwigEnvironmentCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $twigEnvironment = $container->findDefinition('twig');
        // Symfony service subscriber somehow doesn't work. Therefore, the service has to be public
        $twigEnvironment->setPublic(true);
        $twigEnvironment->setClass(TwigEnvironment::class);
        $twigEnvironment->addMethodCall('configureAppTemplateFailureHandling', [
            new Reference(EntityTemplateLoader::class),
            new Reference('logger'),
            new Reference('request_stack'),
        ]);

        $twigEnvironment->addTag('kernel.reset', ['method' => 'reset']);

        // The twig extension directly compiles the config into the service, there is no other way to get it @see \Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension::load
        $twigOptions = $twigEnvironment->getArgument(1);
        \assert(\is_array($twigOptions));

        $configuredTwigCache = $twigOptions['cache'] ?? false;
        if (!\is_string($configuredTwigCache)) {
            $container->setParameter('twig.cache', $container->getParameter('kernel.cache_dir') . '/twig');

            return;
        }

        $container->setParameter('twig.cache', $configuredTwigCache);
    }
}
