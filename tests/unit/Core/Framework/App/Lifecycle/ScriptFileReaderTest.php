<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;

/**
 * @internal
 */
#[CoversClass(ScriptFileReader::class)]
class ScriptFileReaderTest extends TestCase
{
    public function testGetScriptPathsForApp(): void
    {
        $scriptReader = new ScriptFileReader(new StaticSourceResolver([
            'SwagExampleTest' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
        ]));

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        $scripts = $scriptReader->getScriptPathsForApp($app);
        \sort($scripts);

        static::assertEquals(
            [
                'app-activated/activate-script.twig',
                'app-deactivated/deactivate-script.twig',
                'app-deleted/delete-script.twig',
                'app-installed/install-script.twig',
                'app-updated/update-script.twig',
                'product-page-loaded/product-page-script.twig',
            ],
            $scripts
        );
    }

    public function testGetScriptPathsForAppWhenScriptDirDoesntExist(): void
    {
        $scriptReader = new ScriptFileReader(new StaticSourceResolver([
            'SwagExampleTest' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/minimal'),
        ]));

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        static::assertSame(
            [],
            $scriptReader->getScriptPathsForApp($app)
        );
    }

    public function testGetScriptContent(): void
    {
        $scriptReader = new ScriptFileReader(new StaticSourceResolver([
            'SwagExampleTest' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
        ]));

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/scripts/product-page-loaded/product-page-script.twig',
            $scriptReader->getScriptContent($app, 'product-page-loaded/product-page-script.twig')
        );
    }

    public function testGetScriptContentThrowsOnNotFoundFile(): void
    {
        static::expectException(\RuntimeException::class);

        $scriptReader = new ScriptFileReader(new StaticSourceResolver([
            'SwagExampleTest' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
        ]));

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        $scriptReader->getScriptContent($app, 'does/not/exist');
    }
}
