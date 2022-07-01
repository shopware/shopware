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
        $console = new ConsoleOutput();
        $console->writeln('Creating database and import data');

        (new TestBootstrapper())
            ->setForceInstall(true)
            ->setPlatformEmbedded(false)
            ->bootstrap();

        (new Fixtures())->load();
    }

    public function configure(OptionsResolver $resolver): void
    {
    }
}