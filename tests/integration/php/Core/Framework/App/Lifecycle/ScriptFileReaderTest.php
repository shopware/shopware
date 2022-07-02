<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReaderInterface;

/**
 * @internal
 */
class ScriptFileReaderTest extends TestCase
{
    private ScriptFileReaderInterface $scriptReader;

    public function setUp(): void
    {
        $this->scriptReader = new ScriptFileReader(\dirname(__DIR__) . '/');
    }

    public function testGetScriptPathsForApp(): void
    {
        $scripts = $this->scriptReader->getScriptPathsForApp('Manifest/_fixtures/test');
        sort($scripts);

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
        static::assertEquals(
            [],
            $this->scriptReader->getScriptPathsForApp(__DIR__ . '/../Manifest/_fixtures/minimal')
        );
    }

    public function testGetScriptContent(): void
    {
        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/scripts/product-page-loaded/product-page-script.twig',
            $this->scriptReader->getScriptContent('product-page-loaded/product-page-script.twig', 'Manifest/_fixtures/test')
        );
    }

    public function testGetScriptContentThrowsOnNotFoundFile(): void
    {
        static::expectException(\RuntimeException::class);
        $this->scriptReader->getScriptContent('does/not/exist', __DIR__ . '/../Manifest/_fixtures/test');
    }
}
