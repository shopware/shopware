<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptTwigLoader;
use Twig\Error\LoaderError;

/**
 * @internal
 */
class ScriptTwigLoaderTest extends TestCase
{
    private Script $script;

    private ScriptTwigLoader $scriptLoader;

    protected function setUp(): void
    {
        $this->script = new Script(
            'simple-function-case.twig',
            file_get_contents(__DIR__ . '/_fixtures/simple-function-case/Resources/scripts/simple-function-case/simple-function-case.twig'),
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
