<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig\Extension;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('discovery')]
class SlugifyExtensionTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('sampleAnchorIdProvider')]
    public function testSlugifyAnchorIds(?string $input, ?string $expected): void
    {
        static::assertSame($expected, $this->renderTestTemplate($input), 'Slugify needed for plugins missing or invalid.');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function sampleAnchorIdProvider(): iterable
    {
        yield 'empty anchor id stays empty' => ['', ''];
        yield 'single word anchor id stays unchanged' => ['Hello', 'Hello'];
        yield 'spaces in anchor id are replaced with dashes' => ['Hello World', 'Hello-World'];
        yield 'umlauts in anchor id are transliterated' => ['Hëllö Wörld', 'Helloe-Woerld'];
        yield 'German sharp s in anchor id is transliterated' => ['Schokolade in Maßen verzehren', 'Schokolade-in-Massen-verzehren'];
        yield 'French accents in anchor id are transliterated' => ['Je détest les caractères spéciaux', 'Je-detest-les-caracteres-speciaux'];
    }

    private function renderTestTemplate(?string $input): string
    {
        // an own environment with the container-built extension (its `slugify` service carries the transliteration
        // rulesets): rendering through the shared `twig` would cache its request-dependent Storefront globals empty
        $twig = new Environment(new ArrayLoader(['test.html.twig' => '{{ anchorId|slugify }}']));
        $twig->addExtension(static::getContainer()->get(SlugifyExtension::class));

        return $twig->render('test.html.twig', ['anchorId' => $input]);
    }
}
