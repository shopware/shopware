<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Framework\Log\Package;
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
        $templateSource = file_get_contents(self::TEMPLATE_PATH);
        static::assertIsString($templateSource);

        $twig = new Environment(new ArrayLoader([
            '@Framework/garan/label.svg.twig' => $templateSource,
        ]));

        $renderer = new GaranLabelRenderer($twig);

        $result = $renderer->render('3', 'Acme', 'ACME-123');

        static::assertStringContainsString('Acme', $result);
        static::assertStringContainsString('ACME-123', $result);
        static::assertStringContainsString('3', $result);
        static::assertStringContainsString('<svg', $result);
    }

    public function testRenderNestedLabelIncludesGuarantee(): void
    {
        $templateSource = file_get_contents(self::NESTED_TEMPLATE_PATH);
        static::assertIsString($templateSource);

        $twig = new Environment(new ArrayLoader([
            '@Framework/garan/nested-label.svg.twig' => $templateSource,
        ]));

        $renderer = new GaranLabelRenderer($twig);

        $result = $renderer->renderNestedLabel('3');

        static::assertStringContainsString('3', $result);
        static::assertStringContainsString('<svg', $result);
    }
}
