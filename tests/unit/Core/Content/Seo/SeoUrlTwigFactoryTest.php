<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlTwigFactory;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTwigFactory::class)]
class SeoUrlTwigFactoryTest extends TestCase
{
    public function testCreateTwigEnvironment(): void
    {
        // the cache is only configured here, no template is compiled into it
        $twig = $this->createTwigEnvironment('/cache');

        static::assertTrue($twig->hasExtension(SlugifyExtension::class));
        static::assertTrue($twig->hasExtension(PhpSyntaxExtension::class));
        static::assertTrue($twig->hasExtension(SecurityExtension::class));
        static::assertInstanceOf(ArrayLoader::class, $twig->getLoader());
        static::assertTrue($twig->isStrictVariables());
        static::assertInstanceOf(FilesystemCache::class, $twig->getCache());
    }

    public function testCreateTwigEnvironmentWithoutCacheDirDisablesTheCache(): void
    {
        $twig = $this->createTwigEnvironment('');

        static::assertFalse($twig->getCache());
    }

    /**
     * @param array<string, mixed> $product
     */
    #[DataProvider('slugifyEscaperProvider')]
    public function testSlugifyEscaperRendersSlugs(string $field, array $product, string $expected): void
    {
        $twig = $this->createTwigEnvironment('');

        $template = $twig->createTemplate('{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.' . $field . ' }}{% endautoescape %}');

        static::assertSame($expected, $template->render(['product' => $product]));
    }

    public static function slugifyEscaperProvider(): \Generator
    {
        yield 'string' => ['name', ['name' => 'hello world'], 'hello-world'];

        yield 'float' => ['name', ['name' => 01.2024], '1-2024'];

        yield 'string with number' => ['name', ['name' => 'Hello 01.2024'], 'hello-01-2024'];

        yield 'array renders empty' => ['options', ['options' => ['red', 'green']], ''];
    }

    private function createTwigEnvironment(string $cacheDir): Environment
    {
        $slugify = new Slugify();

        return (new SeoUrlTwigFactory())->createTwigEnvironment($slugify, [
            new SlugifyExtension($slugify),
            new PhpSyntaxExtension(),
            new SecurityExtension([]),
        ], $cacheDir);
    }
}
