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
        yield 'sample anchor id scenario 1' => ['', ''];
        yield 'sample anchor id hello hello' => ['Hello', 'Hello'];
        yield 'sample anchor id hello world hello world' => ['Hello World', 'Hello-World'];
        yield 'sample anchor id h ll w rld helloe woerld' => ['Hëllö Wörld', 'Helloe-Woerld'];
        yield 'sample anchor id schokolade in ma en verzehren schokolade in massen verzehren' => ['Schokolade in Maßen verzehren', 'Schokolade-in-Massen-verzehren'];
        yield 'sample anchor id je d test les caract je detest les caracteres' => ['Je détest les caractères spéciaux', 'Je-detest-les-caracteres-speciaux'];
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
