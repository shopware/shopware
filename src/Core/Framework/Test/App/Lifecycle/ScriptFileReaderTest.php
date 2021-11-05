<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReaderInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ScriptFileReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ScriptFileReaderInterface $scriptReader;

    public function setUp(): void
    {
        $this->scriptReader = $this->getContainer()->get(ScriptFileReader::class);
    }

    public function testGetScriptPathsForApp(): void
    {
        $scripts = $this->scriptReader->getScriptPathsForApp(__DIR__ . '/../Manifest/_fixtures/test');

        static::assertEquals(
            ['product-page-loaded/product-page-script.twig'],
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
            $this->scriptReader->getScriptContent('product-page-loaded/product-page-script.twig', __DIR__ . '/../Manifest/_fixtures/test')
        );
    }

    public function testGetScriptContentThrowsOnNotFoundFile(): void
    {
        static::expectException(\RuntimeException::class);
        $this->scriptReader->getScriptContent('does/not/exist', __DIR__ . '/../Manifest/_fixtures/test');
    }
}
