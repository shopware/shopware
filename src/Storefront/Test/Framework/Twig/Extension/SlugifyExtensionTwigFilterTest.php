<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class SlugifyExtensionTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider sampleAnchorIdProvider
     */
    public function testSlugifyAnchorIds(?string $input, ?string $expected): void
    {
        static::assertEquals($expected, $this->renderTestTemplate($input), 'Slugify needed for plugins missing or invalid.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function sampleAnchorIdProvider(): array
    {
        return [
            ['', ''],
            ['Hello', 'Hello'],
            ['Hello World', 'Hello-World'],
            ['Hëllö Wörld', 'Helloe-Woerld'],
            ['Schokolade in Maßen verzehren', 'Schokolade-in-Massen-verzehren'],
            ['Je détest les caractères spéciaux', 'Je-detest-les-caracteres-speciaux'],
        ];
    }

    private function renderTestTemplate(?string $input): string
    {
        $twig = $this->getContainer()->get('twig');

        $originalLoader = $twig->getLoader();
        $twig->setLoader(new ArrayLoader([
            'test.html.twig' => '{{ anchorId|slugify }}',
        ]));
        $output = $twig->render('test.html.twig', ['anchorId' => $input]);
        $twig->setLoader($originalLoader);

        return $output;
    }
}
