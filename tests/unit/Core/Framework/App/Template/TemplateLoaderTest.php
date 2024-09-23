<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;

/**
 * @internal
 */
#[CoversClass(TemplateLoader::class)]
class TemplateLoaderTest extends TestCase
{
    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }

    public function testGetTemplatePathsForApp(): void
    {
        $templateLoader = new TemplateLoader(
            new StaticSourceResolver([
                'test' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
            ])
        );

        $templates = $templateLoader->getTemplatePathsForApp($this->manifest);
        \sort($templates);

        static::assertEquals(
            ['storefront/layout/header/header.html.twig', 'storefront/layout/header/logo.html.twig', 'storefront/page/sitemap/sitemap.xml.twig'],
            $templates
        );
    }

    public function testGetTemplatePathsForAppWhenViewDirDoesntExist(): void
    {
        $templateLoader = new TemplateLoader(new StaticSourceResolver([]));

        static::assertSame(
            [],
            $templateLoader->getTemplatePathsForApp($this->manifest)
        );
    }

    public function testGetTemplateContent(): void
    {
        $templateLoader = new TemplateLoader(
            new StaticSourceResolver([
                'test' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
            ])
        );

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/storefront/layout/header/logo.html.twig',
            $templateLoader->getTemplateContent('storefront/layout/header/logo.html.twig', $this->manifest)
        );
    }

    public function testGetTemplateContentThrowsOnNotFoundFile(): void
    {
        $templateLoader = new TemplateLoader(new StaticSourceResolver([]));

        static::expectException(\RuntimeException::class);
        $templateLoader->getTemplateContent('does/not/exist', $this->manifest);
    }
}
