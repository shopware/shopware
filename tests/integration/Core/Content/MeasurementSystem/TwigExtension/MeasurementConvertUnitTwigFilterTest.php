<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MeasurementSystem\TwigExtension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
class MeasurementConvertUnitTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Environment $twig;

    protected function setUp(): void
    {
        // an own environment with the container-built filter: rendering through the shared `twig` would cache its
        // request-dependent Storefront globals empty for every later test
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->addExtension(static::getContainer()->get(MeasurementConvertUnitTwigFilter::class));
    }

    public function testConvertUnitFilterCanBeUsedInTwigTemplate(): void
    {
        $template = $this->twig->createTemplate('{{ 1000|sw_convert_unit("mm", "m") }}');

        static::assertSame('1 m', $template->render());
    }
}
