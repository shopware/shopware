<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

use PhpBench\DependencyInjection\Container;
use PhpBench\DependencyInjection\ExtensionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @internal - only for performance benchmarks
 */
abstract class AbstractGroupAwareExtension implements ExtensionInterface
{
    public const TARGET_GROUP = null;

    protected OptionsResolver $resolver;

    /**
     * Should only be called if the phpbench run matches the TARGET_GROUP const (isolate commercial features)
     * If fixtures are to be created a "data.json" file must be situated in the same directory
     *
     * @inheritDoc
     */
    public function load(Container $container): void
    {
        $dataPath = __DIR__ . '/data.json';
        (new Fixtures())->load($dataPath);
    }

    /**
     * @inheritDoc
     */
    public function configure(OptionsResolver $resolver): void
    {
    }
}
