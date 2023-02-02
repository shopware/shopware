<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @internal - only for performance benchmarks
 */
class BenchExtension implements ExtensionInterface
{
    public function load(Container $container): void
    {
        $_SERVER['APP_ENV'] = 'test';

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
