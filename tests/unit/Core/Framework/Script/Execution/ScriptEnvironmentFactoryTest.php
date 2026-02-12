<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Twig\Cache\NullCache;
use Twig\Extension\DebugExtension;

/**
 * @internal
 */
#[CoversClass(ScriptEnvironmentFactory::class)]
class ScriptEnvironmentFactoryTest extends TestCase
{
    public function testInitEnv(): void
    {
        $debug = new DebugExtension();
        $syntaxExtension = new PhpSyntaxExtension();
        $factory = new ScriptEnvironmentFactory($debug, [$syntaxExtension], '6.7.0');

        $script = new Script('s', '{{ 1 }}', new \DateTimeImmutable());
        $cache = new NullCache();
        $script->setTwigOptions(['cache' => $cache, 'debug' => false]);

        $env = $factory->initEnv($script);

        // globals
        $globals = $env->getGlobals();
        static::assertArrayHasKey('shopware', $globals);
        static::assertInstanceOf(ArrayStruct::class, $globals['shopware']);
        static::assertSame('6.7.0', $globals['shopware']->get('version'));

        // extension added
        static::assertSame($syntaxExtension, $env->getExtension(PhpSyntaxExtension::class));
        // debug is false, therefore no debug extension
        static::assertFalse($env->hasExtension(DebugExtension::class));

        // cache is set
        static::assertSame($cache, $env->getCache());
    }

    public function testCacheDistinctForDifferentScripts(): void
    {
        $factory = new ScriptEnvironmentFactory(new DebugExtension(), [], '6.7.0');

        $script = new Script('s', '{{ 1 }}', new \DateTimeImmutable());
        $env1 = $factory->initEnv($script);
        $env2 = $factory->initEnv($script);

        static::assertSame($env1, $env2);

        $script2 = new Script('s', '{{ 2 }}', new \DateTimeImmutable());
        $env3 = $factory->initEnv($script2);

        static::assertNotSame($env1, $env3);
    }

    public function testReset(): void
    {
        $factory = new ScriptEnvironmentFactory(new DebugExtension(), [], '6.7.0');

        $script = new Script('s', '{{ 1 }}', new \DateTimeImmutable());

        $env1 = $factory->initEnv($script);
        $env2 = $factory->initEnv($script);

        static::assertSame($env1, $env2);

        $factory->reset();

        $env3 = $factory->initEnv($script);
        static::assertNotSame($env1, $env3);
    }

    public function testAddsDebugExtensionWhenDebugOptionTrue(): void
    {
        $debug = new DebugExtension();
        $factory = new ScriptEnvironmentFactory($debug, [], '6.7.0');

        $script = new Script('s', '{{ 1 }}', new \DateTimeImmutable());
        $script->setTwigOptions(['debug' => true]);

        $env = $factory->initEnv($script);

        static::assertSame($debug, $env->getExtension(DebugExtension::class));
    }
}
