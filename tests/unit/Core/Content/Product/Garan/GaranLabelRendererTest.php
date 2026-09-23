<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\Garan\GaranLabelTwigFilter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelRenderer::class)]
class GaranLabelRendererTest extends TestCase
{
    private const TEMPLATE_PATH = __DIR__ . '/../../../../../../src/Core/Framework/Resources/views/garan/label.svg.twig';

    private const NESTED_TEMPLATE_PATH = __DIR__ . '/../../../../../../src/Core/Framework/Resources/views/garan/nested-label.svg.twig';

    public function testRenderIncludesManufacturerProductNumberAndGuarantee(): void
    {
        $result = $this->createRenderer()->render('3', 'Acme', 'ACME-123');

        static::assertStringContainsString('Acme', $result);
        static::assertStringContainsString('ACME-123', $result);
        static::assertStringContainsString('3', $result);
        static::assertStringContainsString('<svg', $result);
    }

    public function testRenderNestedLabelIncludesGuarantee(): void
    {
        $result = $this->createRenderer()->renderNestedLabel('3');

        static::assertStringContainsString('3', $result);
        static::assertStringContainsString('<svg', $result);
    }

    public function testRenderStartsWithTheXmlDeclaration(): void
    {
        $renderer = $this->createRenderer();

        static::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $renderer->render('3', 'Acme', 'ACME-123'));
        static::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $renderer->renderNestedLabel('3'));
    }

    public function testValuesThatFitAreRenderedWithoutFittingAttributes(): void
    {
        $result = $this->createRenderer()->render('25', 'Acme', 'ACME-123');

        static::assertStringNotContainsString('textLength', $result);
        static::assertStringNotContainsString('lengthAdjust', $result);
    }

    #[DataProvider('overflowingLabelProvider')]
    public function testValuesWiderThanTheArtworkAreFittedToTheirBox(
        string $guarantee,
        string $manufacturer,
        string $productNumber,
        string $expected,
    ): void {
        $result = $this->createRenderer()->render($guarantee, $manufacturer, $productNumber);

        static::assertStringContainsString($expected, $result);
        static::assertStringContainsString('lengthAdjust="spacingAndGlyphs"', $result);
    }

    public static function overflowingLabelProvider(): \Generator
    {
        yield 'a half year duration is squeezed clear of the calendar symbol' => [
            '2,5', 'Acme', 'ACME-123', 'textLength="114.9"',
        ];
        yield 'the duration from the issue report' => [
            '11,5', 'Acme', 'ACME-123', 'textLength="114.9"',
        ];
        yield 'the widest duration 306 months can produce' => [
            '25,5', 'Acme', 'ACME-123', 'textLength="114.9"',
        ];
        yield 'a brand is squeezed clear of the model identifier column' => [
            '3', 'Shopware Lebensmittel und Nahrungsmittel GmbH', 'ACME-123', 'textLength="188.93"',
        ];
        yield 'a model identifier is squeezed clear of the label edge' => [
            '3', 'Acme', '1101101101101101', 'textLength="64.72"',
        ];
    }

    public function testNestedGuaranteeWiderThanTheArtworkIsFittedToItsBox(): void
    {
        $renderer = $this->createRenderer();

        static::assertStringNotContainsString('textLength', $renderer->renderNestedLabel('25'));

        $result = $renderer->renderNestedLabel('25,5');
        static::assertStringContainsString('textLength="58.98"', $result);
        static::assertStringContainsString('lengthAdjust="spacingAndGlyphs"', $result);
    }

    public function testEveryEditableFieldIsClipped(): void
    {
        $result = $this->createRenderer()->render('3', 'Acme', 'ACME-123');

        static::assertStringContainsString('clip-path="url(#label-clippath-manufacturer)"', $result);
        static::assertStringContainsString('clip-path="url(#label-clippath-product-number)"', $result);
        static::assertStringContainsString('clip-path="url(#label-clippath-guarantee)"', $result);

        static::assertStringContainsString(
            'clip-path="url(#garan-nested-clippath-guarantee)"',
            $this->createRenderer()->renderNestedLabel('3')
        );
    }

    public function testMerchantValuesCannotBreakOutOfTheTextElement(): void
    {
        $result = $this->createRenderer()->render('3', '</text><script>alert(1)</script><text>', '"><a>x</a>');

        static::assertStringNotContainsString('<script>', $result);
        static::assertStringContainsString('&lt;/text&gt;&lt;script&gt;', $result);
        static::assertStringContainsString('&quot;&gt;&lt;a&gt;', $result);
    }

    private function createRenderer(): GaranLabelRenderer
    {
        $label = file_get_contents(self::TEMPLATE_PATH);
        static::assertIsString($label);

        $nestedLabel = file_get_contents(self::NESTED_TEMPLATE_PATH);
        static::assertIsString($nestedLabel);

        $twig = new Environment(new ArrayLoader([
            '@Framework/garan/label.svg.twig' => $label,
            '@Framework/garan/nested-label.svg.twig' => $nestedLabel,
        ]));

        $renderer = new GaranLabelRenderer($twig);
        $durationFormatter = new GaranLabelDurationFormatter();

        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([], new ProductDefinition());

        // The templates measure their own values, so they need the filter the extension provides.
        $twig->addExtension(new GaranLabelTwigFilter(
            $durationFormatter,
            $productRepository,
            new GaranLabelResolver($durationFormatter, $renderer),
        ));

        return $renderer;
    }
}
