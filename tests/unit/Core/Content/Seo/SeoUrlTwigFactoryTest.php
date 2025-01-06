<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlTwigFactory;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Cache\FilesystemCache;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SeoUrlTwigFactory::class)]
class SeoUrlTwigFactoryTest extends TestCase
{
    public function testCreateTwigEnvironment(): void
    {
        $fs = (new Filesystem());
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('twig-cache', false));
        $fs->mkdir($tmpDir);

        $factory = new SeoUrlTwigFactory();
        $twig = $factory->createTwigEnvironment(new Slugify(), [], $tmpDir);

        static::assertTrue($twig->hasExtension(SlugifyExtension::class));
        static::assertTrue($twig->hasExtension(PhpSyntaxExtension::class));
        static::assertTrue($twig->hasExtension(SecurityExtension::class));
        static::assertInstanceOf(ArrayLoader::class, $twig->getLoader());
        static::assertTrue($twig->isStrictVariables());
        static::assertInstanceOf(FilesystemCache::class, $twig->getCache());

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('hello-world', $template->render(['product' => ['name' => 'hello world']]));

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('1-2024', $template->render(['product' => ['name' => 01.2024]]));

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('hello-01-2024', $template->render(['product' => ['name' => 'Hello 01.2024']]));

        $fs->remove($tmpDir);
    }
}
