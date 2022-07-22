<?php

namespace Shopware\Tests\Bench;

use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Shopware\Core\TestBootstrapper;

class BenchExtension implements ExtensionInterface
{
    public function load(Container $container): void
    {
        if (isset($_SERVER['DATABASE_URL'])) {
            $url = $_SERVER['DATABASE_URL'];
        }

        (new TestBootstrapper())
            ->setOutput(new ConsoleOutput())
            ->setForceInstall(true)
            ->setPlatformEmbedded(false)
            ->setBypassFinals(false)
            ->bootstrap();

        (new Fixtures())->load();

        if (isset($url)) {
            $_SERVER['DATABASE_URL'] = $url;
        }
    }

    public function configure(OptionsResolver $resolver): void
    {
    }
}