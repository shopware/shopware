<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Executor;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\ExecutableScript;
use Shopware\Core\Framework\Script\Executor\ScriptTwigLoader;
use Twig\Error\LoaderError;

class ScriptTwigLoaderTest extends TestCase
{
    private ExecutableScript $script;

    private ScriptTwigLoader $scriptLoader;

    public function setUp(): void
    {
        $this->script = new ExecutableScript(
            'product-page-loaded.product-page-script.twig',
            file_get_contents(__DIR__ . '/../Registry/_fixtures/apps/test/Resources/scripts/product-page-loaded/product-page-script.twig'),
            new \DateTimeImmutable(),
            []
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

    public function testGetCacheKey(): void
    {
        static::assertEquals(
            $this->script->getName(),
            $this->scriptLoader->getCacheKey($this->script->getName())
        );
    }

    public function testIsFresh(): void
    {
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
}
