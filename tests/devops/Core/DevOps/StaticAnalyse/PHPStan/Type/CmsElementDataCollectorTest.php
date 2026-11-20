<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CmsElementDataPreparation;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/data/CmsElements/Resolvers.php';

/**
 * @internal
 */
#[Package('framework')]
class CmsElementDataCollectorTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = $this->filesystem->tempnam(sys_get_temp_dir(), 'cms-inference-');
        $this->filesystem->remove($this->directory);
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testInfersInheritedAndHelperWritesPreservesShapesAndDoesNotGuessOpaqueMutations(): void
    {
        $this->filesystem->copy(__DIR__ . '/data/CmsElements/container.xml', $this->directory . '/var/cache/static_phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml');
        $root = \dirname(__DIR__, 7);
        CmsElementDataPreparation::prepare($this->directory, $root . '/vendor/bin/phpstan', $root . '/vendor/autoload.php');
        $mapping = json_decode($this->filesystem->readFile($this->directory . '/var/cache/phpstan-cms/context.json'), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame('\\' . TextStruct::class, $mapping['types']['custom-inherited']);
        static::assertSame('\\Shopware\\Core\\Framework\\Struct\\ArrayStruct<array{visible: bool, count: int}>', $mapping['types']['custom-shape']);
        static::assertSame('\\' . ImageStruct::class . '|\\' . TextStruct::class, $mapping['types']['custom-union']);
        static::assertSame(Struct::class, $mapping['types']['custom-opaque']);
        static::assertSame(Struct::class, $mapping['types']['custom-external']);
        static::assertSame(Struct::class, $mapping['types']['custom-callable']);
        static::assertCount(3, $mapping['unresolved']);
    }

    public function testUnknownResolverNamesKeepEveryLookupBroad(): void
    {
        $this->filesystem->copy(__DIR__ . '/data/CmsElements/unknown-container.xml', $this->directory . '/var/cache/static_phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml');
        $root = \dirname(__DIR__, 7);
        CmsElementDataPreparation::prepare($this->directory, $root . '/vendor/bin/phpstan', $root . '/vendor/autoload.php');
        $mapping = json_decode($this->filesystem->readFile($this->directory . '/var/cache/phpstan-cms/context.json'), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame([], $mapping['types']);
        static::assertCount(1, $mapping['unresolved']);
    }
}
