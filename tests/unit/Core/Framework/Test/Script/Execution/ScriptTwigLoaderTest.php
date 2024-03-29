<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Test\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptTwigLoader;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[CoversClass(ScriptTwigLoader::class)]
class ScriptTwigLoaderTest extends TestCase
{
    private Script $script;

    private ScriptTwigLoader $scriptLoader;

    protected function setUp(): void
    {
        $script = file_get_contents(__DIR__ . '/_fixtures/simple-function-case/Resources/scripts/simple-function-case/simple-function-case.twig');

        $this->script = new Script(
            'simple-function-case.twig',
            (string) $script,
            new \DateTimeImmutable()
        );

        $this->scriptLoader = new ScriptTwigLoader($this->script);
    }

    public function testGetSourceContext(): void
    {
        $source = $this->scriptLoader->getSourceContext($this->script->getName());

        static::assertEquals(
            $this->script->getName(),
            $source->getName()
        );
        static::assertEquals(
            $this->script->getScript(),
            $source->getCode()
        );
    }

    public function testGetSourceContextThrowsOnNotFoundScript(): void
    {
        static::expectException(LoaderError::class);
        $this->scriptLoader->getSourceContext('notExisting');
    }

    public function testIsFresh(): void
    {
        static::assertInstanceOf(\DateTimeImmutable::class, $this->script->getLastModified());

        $beforeLastModified = $this->script->getLastModified()->sub(new \DateInterval('PT1S'));
        $afterLastModified = $this->script->getLastModified()->add(new \DateInterval('PT1S'));

        static::assertFalse($this->scriptLoader->isFresh($this->script->getName(), $beforeLastModified->getTimestamp()));
        static::assertTrue($this->scriptLoader->isFresh($this->script->getName(), $afterLastModified->getTimestamp()));

        static::assertFalse($this->scriptLoader->isFresh('doesNotExist', $afterLastModified->getTimestamp()));
    }

    public function testExists(): void
    {
        static::assertTrue($this->scriptLoader->exists($this->script->getName()));
        static::assertFalse($this->scriptLoader->exists('doesNotExist'));
    }

    #[DataProvider('cachingProvider')]
    public function testDifferentCacheKey(Script $first, Script $second, bool $expected): void
    {
        $actual = (new ScriptTwigLoader($first))->getCacheKey('some-include')
        === (new ScriptTwigLoader($second))->getCacheKey('some-include');

        static::assertSame($expected, $actual);
    }

    public static function cachingProvider(): \Generator
    {
        yield 'Shared cache for same names' => [
            new DummyScript('foo.twig', null),
            new DummyScript('foo.twig', null),
            'same' => true,
        ];

        yield 'Different cache for different names' => [
            new DummyScript('foo.twig', null),
            new DummyScript('bar.twig', null),
            'same' => false,
        ];

        yield 'Different cache for same names with different app ids' => [
            new DummyScript('foo.twig', 'first-app'),
            new DummyScript('foo.twig', 'second-app'),
            'same' => false,
        ];

        yield 'Same cache for same names with same app ids' => [
            new DummyScript('foo.twig', 'first-app'),
            new DummyScript('foo.twig', 'first-app'),
            'same' => true,
        ];
    }
}

/**
 * @internal
 */
#[Package('core')]
class DummyScript extends Script
{
    public function __construct(
        string $name,
        ?string $appId,
    ) {
        $app = $appId ? new ScriptAppInformation($appId, '', '') : null;

        parent::__construct($name, 'foo', new \DateTimeImmutable(), $app);
    }
}
