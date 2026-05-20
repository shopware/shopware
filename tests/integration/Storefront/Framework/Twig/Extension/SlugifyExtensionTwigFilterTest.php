<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
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
        $twig = static::getContainer()->get('twig');

        $originalLoader = $twig->getLoader();
        $twig->setLoader(new ArrayLoader([
            'test.html.twig' => '{{ anchorId|slugify }}',
        ]));
        $output = $twig->render('test.html.twig', ['anchorId' => $input]);
        $twig->setLoader($originalLoader);

        return $output;
    }
}
