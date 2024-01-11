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
        $factory = new SeoUrlTwigFactory();
        $twig = $factory->createTwigEnvironment(new Slugify());

        static::assertTrue($twig->hasExtension(SlugifyExtension::class));
        static::assertTrue($twig->hasExtension(PhpSyntaxExtension::class));
        static::assertTrue($twig->hasExtension(SecurityExtension::class));
        static::assertInstanceOf(ArrayLoader::class, $twig->getLoader());
        static::assertTrue($twig->isStrictVariables());
        static::assertFalse($twig->getCache());

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('hello-world', $template->render(['product' => ['name' => 'hello world']]));

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('1-2024', $template->render(['product' => ['name' => 01.2024]]));

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ product.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('hello-01-2024', $template->render(['product' => ['name' => 'Hello 01.2024']]));
    }
}
