<?php declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\StorefrontIncludeNodeVisitor;
use Symfony\Component\DependencyInjection\ContainerInterface;

$classLoader = require __DIR__ . '/vendor/autoload.php';
/** @var Kernel $kernel */
$kernel = KernelFactory::create('test', true, $classLoader, new StaticKernelPluginLoader($classLoader));
$kernel->boot();

/** @var ContainerInterface $container */
// @phpstan-ignore symfonyContainer.serviceNotFound (available in the test environment)
$container = $kernel->getContainer()->get('test.service_container');

$twig = $container->get('twig');
$twig->addNodeVisitor(new StorefrontIncludeNodeVisitor(__DIR__ . '/src/Storefront/Resources/views'));

return $twig;
