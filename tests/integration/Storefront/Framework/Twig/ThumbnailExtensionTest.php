<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Storefront\Framework\Twig\Extension\ConfigExtension;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Shopware\Storefront\Framework\Twig\ThumbnailExtension;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Theme\AbstractResolvedConfigLoader;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
class ThumbnailExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersCorrectImageHtml(): void
    {
        $result = $this->renderTemplate('@Storefront/storefront/thumbnail-default.html.twig', [
            'media' => $this->createExampleMedia(),
            'context' => Generator::generateSalesChannelContext(),
        ]);

        // Expect the image to be rendered with the default attributes
        static::assertStringContainsString('src="https://shopware.local/media/cute-cat.webp"', $result);
        static::assertStringContainsString('alt="Very cute cat alt"', $result);
        static::assertStringContainsString('title="Very cute cat title"', $result);
        static::assertStringContainsString('loading="eager"', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersDecorativeImageWithEmptyAltAttr(): void
    {
        $result = $this->renderTemplate('@Storefront/storefront/thumbnail-decorative.html.twig', [
            'media' => $this->createExampleMedia(),
            'context' => Generator::generateSalesChannelContext(),
        ]);

        // Expect the image to be rendered with empty alt attribute
        static::assertStringContainsString('alt=""', $result);

        // Other empty attributes like title are omitted
        static::assertStringNotContainsString('title=""', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersImageWithoutAltAttr(): void
    {
        $result = $this->renderTemplate('@Storefront/storefront/thumbnail-alt-false.html.twig', [
            'media' => $this->createExampleMedia(),
            'context' => Generator::generateSalesChannelContext(),
        ]);

        // Expect the image to be rendered without alt attribute
        static::assertStringNotContainsString('alt=', $result);

        // Other attributes are set
        static::assertStringContainsString('title="Very cute cat title"', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersSrcsetAttrWhenMediaThumbnailsAreGiven(): void
    {
        $result = $this->renderTemplate('@Storefront/storefront/thumbnail-default.html.twig', [
            'media' => $this->createExampleMediaWithThumbnails([280, 400, 800, 1920]),
            'context' => Generator::generateSalesChannelContext(),
        ]);

        static::assertStringContainsString('src="https://shopware.local/media/cute-cat.webp"', $result);
        static::assertStringContainsString('srcset="https://shopware.local/thumbnail/cute-cat_800x800.webp 800w, https://shopware.local/thumbnail/cute-cat_400x400.webp 400w, https://shopware.local/thumbnail/cute-cat_280x280.webp 280w, https://shopware.local/thumbnail/cute-cat_1920x1920.webp 1920w"', $result);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function renderTemplate(string $templatePath, array $data): string
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('StorefrontTest', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('Storefront', \dirname((string) ReflectionHelper::getFileName(Storefront::class))),
        ]);

        $templatePath = $templateFinder->find($templatePath);
        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        return $template->render($data);
    }

    private function createExampleMedia(): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId('test-media-id');
        $media->setUrl('https://shopware.local/media/cute-cat.webp');
        $media->setPath('media/cute-cat.webp');
        $media->setTranslated([
            'title' => 'Very cute cat title',
            'alt' => 'Very cute cat alt',
        ]);

        return $media;
    }

    /**
     * @param array<int> $thumbnailSizes
     */
    private function createExampleMediaWithThumbnails(array $thumbnailSizes): MediaEntity
    {
        $media = $this->createExampleMedia();

        $media->setThumbnails($this->createThumbnails($thumbnailSizes));

        return $media;
    }

    /**
     * @param array<int> $thumbnailSizes
     */
    private function createThumbnails(array $thumbnailSizes): MediaThumbnailCollection
    {
        $thumbnailCollection = new MediaThumbnailCollection();

        foreach ($thumbnailSizes as $size) {
            $thumbnail = new MediaThumbnailEntity();
            $thumbnail->setId('thumb-' . $size);
            $thumbnail->setWidth($size);
            $thumbnail->setHeight($size);
            $thumbnail->setUrl('https://shopware.local/thumbnail/cute-cat_' . $size . 'x' . $size . '.webp');
            $thumbnailCollection->add($thumbnail);
        }

        return $thumbnailCollection;
    }

    /**
     * @param BundleFixture[] $bundles
     *
     * @throws LoaderError
     * @throws Exception
     *
     * @return array{0: Environment, 1: TemplateFinder}
     */
    private function createFinder(array $bundles): array
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $twig = new Environment($loader);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())
            ->method('getBundles')
            ->willReturn($bundles);

        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->expects($this->any())
            ->method('getScopes')
            ->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            sys_get_temp_dir() . '/' . uniqid('twig_test_', true),
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    static::getContainer()->get(Connection::class)
                ),
            ]),
            $scopeDetector,
        );

        // Needed for the ConfigExtension, so the theme_config('breakpoint.sm') calls return the actual breakpoints.
        $templateConfigAccessor = new TemplateConfigAccessor(
            $this->createMock(SystemConfigService::class),
            new ThemeConfigValueAccessor(
                $this->createMock(AbstractResolvedConfigLoader::class),
                $this->createMock(EventDispatcherInterface::class)
            ),
            $this->createMock(ThemeScripts::class)
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));
        $twig->getExtension(NodeExtension::class)->getFinder();
        $twig->addExtension(new ThumbnailExtension($templateFinder));

        // url encoder and theme_config are used inside the thumbnail.html.twig template
        $twig->addExtension(new ConfigExtension($templateConfigAccessor));
        $twig->addExtension(new UrlEncodingTwigFilter());

        return [$twig, $templateFinder];
    }
}
