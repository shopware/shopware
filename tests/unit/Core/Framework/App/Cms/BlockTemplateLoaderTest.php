<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\BlockTemplateLoader;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\Framework\App\Exception\AppCmsExtensionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlockTemplateLoader::class)]
class BlockTemplateLoaderTest extends TestCase
{
    private Io $io;

    private string $root;

    private CmsExtensions $cmsExtensions;

    protected function setUp(): void
    {
        $this->io = new Io();
        $this->root = Path::join(sys_get_temp_dir(), $this->name());
        $this->io->mkdir($this->root . '/app/cms/blocks/my-block');

        $this->cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/Resources/cms.xml');
        $this->cmsExtensions->setPath($this->root . '/app');
    }

    protected function tearDown(): void
    {
        $this->io->remove($this->root);
    }

    public function testReadsBlockFiles(): void
    {
        $this->io->dumpFile($this->root . '/app/cms/blocks/my-block/preview.html', '<div>preview</div>');
        $this->io->dumpFile($this->root . '/app/cms/blocks/my-block/styles.css', '.my-block {}');

        $loader = new BlockTemplateLoader();

        static::assertSame('<div>preview</div>', $loader->getTemplateForBlock($this->cmsExtensions, 'my-block'));
        static::assertSame('.my-block {}', $loader->getStylesForBlock($this->cmsExtensions, 'my-block'));
    }

    public function testRejectsTemplatesOutsideOfTheBlocksDirectory(): void
    {
        $this->io->dumpFile($this->root . '/outside/preview.html', 'secret');

        static::expectExceptionObject(new AppCmsExtensionException('Block "../../../outside" is outside of the cms/blocks directory'));

        (new BlockTemplateLoader())->getTemplateForBlock($this->cmsExtensions, '../../../outside');
    }

    public function testRejectsStylesOutsideOfTheBlocksDirectory(): void
    {
        $this->io->dumpFile($this->root . '/outside/styles.css', 'secret');

        static::expectExceptionObject(new AppCmsExtensionException('Block "../../../outside" is outside of the cms/blocks directory'));

        (new BlockTemplateLoader())->getStylesForBlock($this->cmsExtensions, '../../../outside');
    }
}
