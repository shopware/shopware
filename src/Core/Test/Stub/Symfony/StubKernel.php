<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Symfony;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class StubKernel extends Kernel
{
    /**
     * @param list<BundleInterface> $bundles
     */
    public function __construct(array $bundles = [])
    {
        parent::__construct(
            'test',
            true,
            new ComposerPluginLoader(new ClassLoader(__DIR__)),
            '',
            '',
            new FakeConnection([]),
            __DIR__
        );

        foreach ($bundles as $bundle) {
            $this->bundles[$bundle->getName()] = $bundle;
        }
    }

    public function registerBundles(): iterable
    {
        return $this->bundles;
    }
}
